# Payplug Payments Module

## Installation

### Installation via Magento Back Office

You can follow Magento’s instruction provided at
[https://devdocs.magento.com/guides/v2.3/comp-mgr/extens-man/extensman-main-pg.html](https://devdocs.magento.com/guides/v2.3/comp-mgr/extens-man/extensman-main-pg.html)

### Installation via composer

#### Composer

How to get Composer : 
Please follow instructions on [https://getcomposer.org/download/](https://getcomposer.org/download/)

How to update your Composer version : 
Please follow instructions on [https://getcomposer.org/doc/03-cli.md#self-update-selfupdate-](https://getcomposer.org/doc/03-cli.md#self-update-selfupdate-)

#### Installation

Run the following commands in Magento root directory:

```
composer require payplug/payplug-magento2  # (*)
composer install
php bin/magento module:enable Payplug_Payments --clear-static-content
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy <languages>  # (**)
php bin/magento cache:clean
```

(\*) If you didn’t save them when you installed Magento 2, this command will ask for your Magento authentication keys (https://devdocs.magento.com/guides/v2.3/install-gde/prereq/connect-auth.html).

(\*\*) With the `languages` option, you can define for which language you want to generate your static content. Languages should be separated with a space.
If you are not running on production mode, use the `--force` option. Otherwise the command will fail.

For example, to generate content for locales en_US and fr_FR, you can run the command:
```
php bin/magento setup:static-content:deploy en_US fr_FR  # --force # if you are not running on production mode
```

**Troubleshooting:**

If you get a missing class error message while following the install process:

```
[ReflectionException] Class Payplug\Authentication does not exist
```

It’s likely that the Payplug PHP library was not installed along with the Magento module. This will happen if you did not run composer to install the module.
To fix it, you should require the missing dependency with composer :

```
composer require payplug/payplug-php:^3.0
```

You will then need to install another library which we use to normalize the customers' phone number
```
composer require giggsey/libphonenumber-for-php:^8.10
```

### Update PayPlug Payments Module

Run the following commands in Magento root directory:

```
composer require --update-with-all-dependencies payplug/payplug-magento2:VERSION_YOU_WANT_TO_UPDATE_TO  # (*) # (***)
composer install
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy <languages>  # (**)
php bin/magento cache:clean
```

(\*\*\*) To determine which value for `VERSION_YOU_WANT_TO_UPDATE_TO`, you can check out our [last releases](https://github.com/payplug/payplug-magento2/releases)
For example, you can run: 
```
composer require --update-with-all-dependencies payplug/payplug-magento2:^1.5
```
