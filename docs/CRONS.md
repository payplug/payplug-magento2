# Asynchronous Order Status Updates (from v4.3.0)

Starting with **version 4.3.0**, the Payplug module for Magento introduces an **asynchronous** workflow that runs **in parallel** with the standard (immediate) payment flow. This addition ensures that orders are updated automatically when payment confirmations are delayed by the bank or by Payplug, preventing them from getting stuck in “payment review.”

---

## Why an Asynchronous Workflow?

In production, banks sometimes take longer than a few seconds to confirm a payment. During this delay, Magento places the order in **payment review**. Prior to v4.3.0, if the confirmation arrived late, the order could remain in that status until a manual update was performed.

As of v4.3.0, Magento leverages **Magento’s cron** to periodically check the payment status with Payplug. Once the confirmation is received, Magento automatically updates the order status (e.g., to “processing” or “complete”).

### Key Highlights

- **Immediate Confirmations**: If the payment is confirmed quickly, the order follows the regular workflow (no change).
- **Delayed Confirmations**: If confirmation is delayed, a cron job runs in the background to reconcile the final payment status.

---

## Prerequisites

1. **Magento’s Cron**  
   Ensure your Magento cron jobs are properly configured and actively running.  
   By default, the Payplug cron tasks are assigned to the **`payplug`** cron group.

2. **Regular Scheduling**  
   Set your cron to run at a suitable interval (e.g., every minute) so any delayed confirmations are quickly processed.

---

## Configuring Magento Cron

### Native Cron Installation

If you install cron natively with the `bin/magento cron:install` command (or follow the official [Magento Cron Configuration](https://experienceleague.adobe.com/fr/docs/commerce-operations/configuration-guide/cli/configure-cron-jobs)), Magento automatically creates a single crontab entry that runs all cron groups. For example:

```bash
* * * * * /usr/local/bin/php /var/www/project/magento/bin/magento cron:run 2>&1 | grep -v "Ran jobs by schedule" >> /var/www/project/magento/var/log/magento.cron.log
```

This entry automatically processes **all** cron groups, including `payplug`, and should work flawlessly.

### Custom Crontab Configurations

Some users customize their crontab to run specific cron groups. For example, a customized crontab might include entries like:

```bash
* * * * * /usr/local/bin/php /var/www/project/magento/bin/magento cron:run --group=default 2>&1 | grep -v "Ran jobs by schedule" >> /var/www/project/magento/var/log/magento.cron.log
```

In such cases, **you must add the `payplug` cron group** to your crontab to ensure that the asynchronous order status updates are processed. For example:

```bash
* * * * * /usr/local/bin/php /var/www/project/magento/bin/magento cron:run --group=payplug 2>&1 | grep -v "Ran jobs by schedule" >> /var/www/project/magento/var/log/magento.cron.log
```

This will ensure that the Payplug cron tasks (`payplug_payments_check_order_consistency` and `payplug_payments_auto_capture_deferred_payments`) are executed according to the schedule defined in your `crontab.xml`.

## Dynamic Cron Group Execution

As an alternative approach, you could dynamically list all cron groups declared in your Magento 2 project and execute `bin/magento cron:run --group=<group>` for each one in a single crontab line. (Do not take it as it, it must be carefully tested on your environment.) This method allows any new cron groups added by modules to be automatically included without needing to modify the crontab manually.

### Example Command

```bash
*/5 * * * * www-data bash -c 'for group in $(php /var/www/html/bin/magento cron:show | awk -F "|" "NR>2 {print \$2}" | sort -u); do php /var/www/html/bin/magento cron:run --group=$group; done' >> /var/log/magento_cron.log 2>&1
```

### Explanation

- **Listing Cron Jobs:**  
  `php /var/www/html/bin/magento cron:show` lists all cron jobs along with their groups.

- **Extracting Unique Groups:**  
  `awk -F "|" "NR>2 {print \$2}"` extracts only the group names (ignoring the header), and `sort -u` removes duplicates.

- **Looping Through Groups:**  
  The `for group in $(...); do ...; done` loop iterates over each unique group, running `php /var/www/html/bin/magento cron:run --group=$group` for each.

- **Bash Execution in Crontab:**  
  `bash -c '...'` allows the entire command to be executed by bash.

- **Logging:**  
  The output is redirected to `/var/log/magento_cron.log` for debugging purposes.

> **Note:** This method could be used to automate cron execution by group name, but it should be carefully tested in your environment before being deployed in production. Adjust the user (`www-data`) and path (`/var/www/html/`) as needed for your Magento installation.

---

## Verifying Cron Execution and Logs

1. **Crontab Check**
    - Confirm that your system crontab is correctly set up.
    - Review the output in your designated cron log (e.g., `var/log/magento.cron.log`) to verify that the jobs are running without errors.

2. **Payplug Log File**
    - For Payplug-specific logs, review:
      ```text
      var/log/payplug_payments.log
      ```
    - Any errors or warnings from the Payplug cron tasks will be recorded here.

---

## Summary

- **Change in v4.3.0**: An asynchronous mechanism now reconciles delayed payment confirmations automatically via the `payplug` cron group.
- **No Impact on Standard Workflow**: Orders still follow the immediate confirmation process when payments are processed quickly.
- **Required Merchant Action**:
    1. If you use the native Magento cron installation (`bin/magento cron:install`), everything should work automatically.
    2. If you have a custom crontab that runs specific groups, ensure that you add an entry for the **`payplug`** cron group.
- **Benefit**: This asynchronous flow helps prevent orders from being stuck in “payment review” due to delayed confirmations.

---

## Additional Resources

- [Adobe Commerce / Magento Official Cron Configuration](https://experienceleague.adobe.com/fr/docs/commerce-operations/configuration-guide/cli/configure-cron-jobs)

---

### [<- Back to README](../README.md)
