<?php
// Browser entry point for the read-only audit; no shared cron access rules required.
require __DIR__.'/core/crons/audit_product_memory.php';
