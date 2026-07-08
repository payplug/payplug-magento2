# Asynchronous Processing (order statuses, invoices & refunds)

The Payplug module relies on Magento's cron for **two distinct asynchronous mechanisms**, both of which are required for the module to work correctly:

1. **Order status reconciliation** (from **v4.3.0**) — runs in the **`payplug`** cron group. It ensures that orders are updated automatically when payment confirmations are delayed by the bank or by Payplug, preventing them from getting stuck in “payment review.”
2. **Message queue consumers** for **invoice creation and refunds** — triggered by Magento's native `consumers_runner` job, which lives in the **`default`** cron group. See [Message Queue Consumers (invoices & refunds)](#message-queue-consumers-invoices--refunds) below.

> ⚠️ **Both the `default` and the `payplug` cron groups must run.** Enabling only `payplug` reconciles order statuses but leaves invoices and refunds unprocessed.
>
> **Note:** The **`default`** cron group is not a Payplug-specific requirement — it is a prerequisite of *any* Magento installation. Magento's cron (the `default` group among others) already powers core features such as asynchronous order confirmation emails, reindexing, stock/inventory maintenance and admin grid updates. On a correctly configured store it is therefore **already running**. Before adding a dedicated crontab entry, first verify that it is actually being executed (see [Verifying Cron Execution and Logs](#verifying-cron-execution-and-logs)); only add one if the `default` group is missing.

---

## Why an Asynchronous Workflow?

In production, banks sometimes take longer than a few seconds to confirm a payment. During this delay, Magento places the order in **payment review**. Prior to v4.3.0, if the confirmation arrived late, the order could remain in that status until a manual update was performed.

As of v4.3.0, Magento leverages **Magento’s cron** to periodically check the payment status with Payplug. Once the confirmation is received, Magento automatically updates the order status (e.g., to “processing” or “complete”).

### Key Highlights

- **Immediate Confirmations**: If the payment is confirmed quickly, the order follows the regular workflow (no change).
- **Delayed Confirmations**: If confirmation is delayed, a cron job runs in the background to reconcile the final payment status.

---

## Message Queue Consumers (invoices & refunds)

Beyond order-status reconciliation, the module creates **invoices** and processes **refunds** asynchronously through Magento's message queue. Two consumers are declared in `etc/queue_consumer.xml` (both on the MySQL `db` connection):

| Topic | Consumer | Handler | Purpose |
| --- | --- | --- | --- |
| `payplug.order.invoicing` | `payplug.order.invoicing` | `Payplug\Payments\Service\CreateOrderInvoice::execute` | Creates the invoice for a paid order |
| `payplug.order.refunding` | `payplug.order.refunding` | `Payplug\Payments\Service\CreateOrderRefund::execute` | Creates the credit memo / refund for an order |

When a payment is captured or a refund is received (e.g. via IPN), the module **publishes** a message to the relevant topic instead of processing it inline. The message is then picked up by the corresponding consumer.

Because these consumers use the `db` connection, they are executed by Magento's native **`consumers_runner`** cron job, which belongs to the **`default`** cron group (module `Magento_MessageQueue`). In other words:

- If the **`default`** cron group does **not** run, published messages pile up in the queue and **no invoice or refund is ever created**, even though payments and order statuses look fine.
- This is independent from the `payplug` cron group, which only handles `check_order_consistency` and `auto_capture_deferred_payments`.

> **Note:** `consumers_runner` behavior can be tuned in `app/etc/env.php` (`cron_run`, `max_messages`, `consumers`, `multiple_processes`). Make sure it is not disabled (`cron_run` must not be set to `false`) and, if the `consumers` allow-list is used, that `payplug.order.invoicing` and `payplug.order.refunding` are not excluded.
>
> ⚠️ **Do not configure `multiple_processes` for the Payplug consumers** (`payplug.order.invoicing`, `payplug.order.refunding`). Running several parallel processes for the same consumer can process messages targeting the same order concurrently, leading to race conditions such as duplicated invoices or refunds. Keep these consumers running as a single process.

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

In such cases, **you must make sure that both the `default` and the `payplug` cron groups run**:

- The **`default`** group runs Magento's `consumers_runner` job, which processes the invoice and refund message queue consumers (see [Message Queue Consumers](#message-queue-consumers-invoices--refunds)).
- The **`payplug`** group runs `payplug_payments_check_order_consistency` and `payplug_payments_auto_capture_deferred_payments`.

For example:

```bash
* * * * * /usr/local/bin/php /var/www/project/magento/bin/magento cron:run --group=default 2>&1 | grep -v "Ran jobs by schedule" >> /var/www/project/magento/var/log/magento.cron.log
* * * * * /usr/local/bin/php /var/www/project/magento/bin/magento cron:run --group=payplug 2>&1 | grep -v "Ran jobs by schedule" >> /var/www/project/magento/var/log/magento.cron.log
```

> ⚠️ Running **only** `--group=payplug` reconciles order statuses but leaves the `default` group's `consumers_runner` unexecuted, so **invoices and refunds will never be created**.

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

- **Order status reconciliation (v4.3.0)**: An asynchronous mechanism reconciles delayed payment confirmations automatically via the `payplug` cron group.
- **Invoices & refunds**: These are created asynchronously through Magento message queue consumers, executed by the `consumers_runner` job of the **`default`** cron group.
- **No Impact on Standard Workflow**: Orders still follow the immediate confirmation process when payments are processed quickly.
- **Required Merchant Action**:
    1. If you use the native Magento cron installation (`bin/magento cron:install`), everything should work automatically (all groups run).
    2. If you have a custom crontab that runs specific groups, ensure that **both** the **`default`** group (invoices/refunds via `consumers_runner`) **and** the **`payplug`** group (status reconciliation & auto-capture) are executed.
- **Benefit**: This asynchronous flow prevents orders from being stuck in “payment review” and ensures invoices and refunds are processed reliably.

---

## Additional Resources

- [Adobe Commerce / Magento Official Cron Configuration](https://experienceleague.adobe.com/fr/docs/commerce-operations/configuration-guide/cli/configure-cron-jobs)

---

### [<- Back to README](../README.md)
