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
php bin/magento setup:static-content:deploy <languages>  # (**)(***)
php bin/magento cache:clean
```

(\*) If you didn’t save them when you installed Magento 2, this command will ask for your Magento authentication keys (https://devdocs.magento.com/guides/v2.3/install-gde/prereq/connect-auth.html).
Login = Public Key
Password = Private Key

(\*\*) With the languages option, you can define for which language you want to generate your static content. Languages should be separated with a space. 
For example, to generate content for locales en_US and fr_FR, you can run the command:
```
php bin/magento setup:static-content:deploy en_US fr_FR
```

(\*\*\*) If you are not running on production mode, use the --force option. Otherwise the command will fail.
For example, to generate content for locales en_US and fr_FR, you can run the command:

```
php bin/magento setup:static-content:deploy --force en_US fr_FR  # --force # if you are not running on production mode
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

### Cron Job Configuration

The Payplug Payments module introduces new cron tasks that are grouped under the **`payplug`** cron group in `etc/crontab.xml`. These cron jobs **must** run for the module to function properly.

- **`payplug_payments_check_order_consistency`**: Checks and updates the consistency of orders with Payplug.
- **`payplug_payments_auto_capture_deferred_payments`**: Capture the deferred payments after too much time elapsed.
- If your orders get stuck in **`payment review`** status, it typically indicates that **`payplug_payments_check_order_consistency`** is not running. Be sure to include this cron in your system’s crontab so that it is executed regularly (every 15 min by default, but you can override it to run it more frequently).

#### Verifying Crons and Logs

- Ensure your Magento crontab is correctly configured to run all Magento cron groups, (the **`payplug`** group should be activated by default after a cron:install).
- If you need to investigate any issues with these jobs, you can consult the **`var/log/payplug_payments.log`** file to see detailed logs and errors related to the Payplug Payments module’s cron executions.

For more information on how to properly configure and schedule Magento 2 cron jobs, consult
[Magento’s official documentation](https://experienceleague.adobe.com/fr/docs/commerce-operations/configuration-guide/cli/configure-cron-jobs)

For more information about the new **`payplug_payments_check_order_consistency`** cron, consult
[the Payplug CRON documentation](docs/CRONS.md)


### Update Payplug Payments Module

Run the following commands in Magento root directory:

```
composer require --update-with-all-dependencies payplug/payplug-magento2:VERSION_YOU_WANT_TO_UPDATE_TO  # (*)
composer install
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy <languages>  # (**)
php bin/magento cache:clean
```

(\*) To determine which value for `VERSION_YOU_WANT_TO_UPDATE_TO`, you can check out our [last releases](https://github.com/payplug/payplug-magento2/releases)
For example, you can run: 
```
composer require --update-with-all-dependencies payplug/payplug-magento2:^1.5
```

(\*\*) With the languages option, you can define for which language you want to generate your static content. Languages should be separated with a space. 
For example, to generate content for locales en_US and fr_FR, you can run the command:
```
php bin/magento setup:static-content:deploy en_US fr_FR
```
