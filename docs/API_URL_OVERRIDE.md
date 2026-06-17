# Support de la surcharge d'URL API — notes techniques

Ce document décrit les changements apportés au module pour permettre de pointer vers des environnements PayPlug non-production (QA, staging, interne) sans modifier le code.

---

## Contexte et problème

La lib `payplug-php` (vendor) initialise ses URLs statiquement à la fin de `APIRoutes.php` :

```php
// lib/Payplug/Core/APIRoutes.php
APIRoutes::$API_BASE_URL    = 'https://api.payplug.com';
APIRoutes::$SERVICE_BASE_URL = 'https://retail.service.payplug.com';
```

Ces deux propriétés statiques étaient jamais surchargées par le module Magento. Résultat : même configuré en "mode TEST", le module envoyait toutes ses requêtes sur l'API production PayPlug — seule la clé API différait. Il était impossible de pointer vers un environnement QA/staging sans toucher au vendor.

La lib expose pourtant deux méthodes prévues pour ça :

```php
APIRoutes::setApiBaseUrl($url);
APIRoutes::setServiceBaseUrl($url);
```

---

## Solution implémentée

### Nouvelle méthode `initApiUrls()` dans `Helper/Config.php`

```php
public function initApiUrls(): void
{
    $apiBaseUrl     = getenv('PAYPLUG_API_BASE_URL');
    $serviceBaseUrl = getenv('PAYPLUG_SERVICE_BASE_URL');

    if ($apiBaseUrl)     APIRoutes::setApiBaseUrl($apiBaseUrl);
    if ($serviceBaseUrl) APIRoutes::setServiceBaseUrl($serviceBaseUrl);
}
```

Quand les variables sont vides ou absentes, rien n'est surchargé — les valeurs de la lib restent actives (comportement prod inchangé).

### Points d'appel

La méthode est appelée juste avant chaque premier appel à l'API, aux quatre endroits d'entrée du module :

| Fichier | Quand c'est appelé |
|---------|-------------------|
| `Helper/Config.php::setPayplugApiKey()` | Tout paiement, remboursement, récupération de transaction |
| `Model/Api/Login.php::login()` et `getAccount()` | Connexion legacy email/mot de passe |
| `Controller/Adminhtml/Config/Oauth2FetchClientData::execute()` | Fin du flow OAuth2 (récupération des credentials) |
| `Service/GetOauth2AccessTokenData::regenerate()` | Renouvellement du JWT OAuth2 |

> `APIRoutes::$API_BASE_URL` et `SERVICE_BASE_URL` sont des propriétés statiques de classe : une seule initialisation par processus PHP suffit. Les appels répétés à `initApiUrls()` sont sans conséquence (idempotent si les vars d'env ne changent pas).

---

## Configuration

### Variables d'environnement

Dans `.env` à la racine du projet Docker :

```dotenv
PAYPLUG_API_BASE_URL=https://api-qa.payplug.com
PAYPLUG_SERVICE_BASE_URL=https://retail.service-qa.payplug.com
```

Ces variables sont transmises au conteneur `apache-php` via `docker-compose.yml` :

```yaml
environment:
  PAYPLUG_API_BASE_URL:     ${PAYPLUG_API_BASE_URL:-}
  PAYPLUG_SERVICE_BASE_URL: ${PAYPLUG_SERVICE_BASE_URL:-}
```

### Comportement selon la valeur de la variable

| Valeur dans `.env` | Comportement |
|-------------------|-------------|
| *(vide ou absente)* | URL prod de la lib (`https://api.payplug.com`) |
| `https://api-qa.payplug.com` | Environnement QA |
| N'importe quelle URL valide | Pointe vers cet endpoint |

---

## Cas particulier : OAuth2 et l'environnement gamma

La lib contient déjà une logique interne pour le flow OAuth2 :

```php
// APIRoutes.php ligne ~62
if (in_array($route, [self::OAUTH2_TOKEN_RESOURCE, self::OAUTH2_AUTH_RESOURCE])
    && false !== strpos(self::$API_BASE_URL, 'https://service.')) {
    self::$API_BASE_URL = 'https://hydra--4444.external.gamma.notpayplug.com';
}
```

Si `PAYPLUG_API_BASE_URL` contient `https://service.`, la lib redirige automatiquement les routes OAuth2 vers l'instance Hydra gamma. Ce comportement pré-existant est conservé.

---

## Fichiers modifiés

```
src/app/code/Payplug/Payments/
├── Helper/Config.php                              ← +initApiUrls(), appelé dans setPayplugApiKey()
├── Model/Api/Login.php                            ← +injection ConfigHelper, appel initApiUrls()
├── Controller/Adminhtml/Config/
│   └── Oauth2FetchClientData.php                  ← appel initApiUrls() (ConfigHelper déjà injecté)
└── Service/GetOauth2AccessTokenData.php           ← +injection ConfigHelper, appel initApiUrls()

docker-compose.yml                                 ← transmission des vars au conteneur PHP
.env                                               ← PAYPLUG_API_BASE_URL, PAYPLUG_SERVICE_BASE_URL
```

---

## Ce qui n'a pas changé

- La distinction test/live dans Magento (`environmentmode`) fonctionne de manière identique — c'est toujours la clé API qui change, pas l'URL.
- Le flow OAuth2 complet (login, logout, refresh token) est couvert.
- Zéro impact si les variables sont vides — comportement prod inchangé.
