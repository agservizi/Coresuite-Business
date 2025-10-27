<?php
if (!isset($customer)) {
    $customer = CustomerAuth::getAuthenticatedCustomer();
}

$pageTitle = $pageTitle ?? 'Pickup Portal';
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$csrfToken = htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8');
$customerName = htmlspecialchars($customer['name'] ?? $customer['email'] ?? 'Cliente', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="it" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= $csrfToken ?>">
    <title><?= htmlspecialchars($pageTitle) ?> · Pickup Portal</title>
    <meta name="description" content="Gestisci i tuoi ritiri con il Pickup Portal di Coresuite Business">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0b2f6b">
    <link rel="icon" type="image/x-icon" href="assets/img/favicon.ico">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet" referrerpolicy="no-referrer">
    <link href="assets/css/portal.css" rel="stylesheet">
</head>
<body class="portal-body">
<div id="portal-app" class="d-flex flex-grow-1">
<script>
window.portalConfig = {
    csrfToken: '<?= $csrfToken ?>',
    customerId: <?= (int) ($customer['id'] ?? 0) ?>,
    apiBaseUrl: 'api/',
    currentPage: '<?= $currentPage ?>'
};
</script>

<div id="global-alert-container" class="container-fluid py-3" style="display: none;">
    <div class="row">
        <div class="col-12">
            <div id="global-alert" class="alert alert-dismissible fade show" role="alert">
                <span id="global-alert-message"></span>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Chiudi"></button>
            </div>
        </div>
    </div>
</div>