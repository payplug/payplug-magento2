# Asynchronous Order Status Updates (from v4.3.0)

Starting with **version 4.3.0**, the Payplug module for Magento introduces an **asynchronous** workflow that runs **in parallel** with the standard (immediate) payment flow. This addition ensures that orders are updated automatically when confirmation is delayed by the bank or by Payplug, preventing them from getting stuck in “payment review.”

---

## Why an Asynchronous Workflow?

In production, banks sometimes take longer than a few seconds to confirm a payment. During this delay, Magento places the order in **payment review**. Prior to v4.3.0, if the confirmation arrived late, the order could remain in that status until a manual update was performed.

As of v4.3.0, we leverage **Magento’s default cron** to periodically check the payment status with Payplug. Once the confirmation is received, Magento automatically updates the order status (e.g., to “processing” or “complete”).

### Key Highlights
- **Immediate Confirmations**: If the payment is confirmed quickly, the order follows the regular workflow (no change).
- **Delayed Confirmations**: If confirmation is delayed, a cron job runs in the background to reconcile the final payment status.

---

## Prerequisites

1. **Magento’s Default Cron**  
   Ensure your Magento cron jobs are properly configured and **actively running**. The Payplug cron tasks are now included in Magento’s default cron group; no separate group configuration is needed.

2. **Regular Scheduling**  
   Set your cron to run at a suitable interval (e.g., every 5 minutes) so any delayed confirmations are quickly picked up.

---

## Configuring the Default Magento Cron

Below is an example of a recommended Magento cron configuration (typically added to your system crontab):

```bash
*/5 * * * * php /path/to/your/site/bin/magento cron:run | grep -v "Ran jobs by schedule" >> /path/to/your/site/var/log/magento.cron.log
*/5 * * * * php /path/to/your/site/update/cron.php >> /path/to/your/site/var/log/update.cron.log
*/5 * * * * php /path/to/your/site/bin/magento setup:cron:run >> /path/to/your/site/var/log/setup.cron.log
```

With this configuration in place, the Payplug cron task—`payplug_payments_check_order_consistency`—will automatically run as part of Magento’s **default cron group**.

> **Note**: If Magento’s cron is disabled or incorrectly configured, orders stuck in “payment review” will not be updated until you manually intervene.

---

## Verifying Cron Execution and Logs

1. **Crontab Check**
    - Confirm that the system crontab is correctly set up.
    - Check the output in `var/log/magento.cron.log` (or your chosen cron log) to ensure the jobs run without errors.

2. **Payplug Log File**
    - For Payplug-specific logs, review:
      ```text
      var/log/payplug_payments.log
      ```
    - Any errors or warnings related to the Payplug cron tasks will be recorded here.

---

## Summary

- **Change in v4.3.0**: An asynchronous mechanism now reconciles delayed payment confirmations automatically via Magento’s default cron.
- **No Impact on Standard Workflow**: Orders still follow the immediate confirmation process when payments go through quickly.
- **Required Merchant Action**:
    1. Ensure Magento cron jobs are configured and running on a reliable schedule.
    2. Monitor the logs if orders remain in “payment review” longer than expected.
- **Benefit**: This asynchronous flow helps avoid orders remaining stuck in “payment review” due to delayed confirmations.

---

### Additional Resources

- [Adobe Commerce / Magento Official Cron Configuration](https://experienceleague.adobe.com/fr/docs/commerce-operations/configuration-guide/cli/configure-cron-jobs)

### [<- Back to Readme](../README.md)
