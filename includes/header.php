<?php
if (!isset($pageTitle)) {
    $pageTitle = 'Coresuite Business';
}
$csrfToken = csrf_token();
$flashMessages = get_flashes();
?>
<!DOCTYPE html>
<html lang="it" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo $csrfToken; ?>">
    <title><?php echo sanitize_output($pageTitle); ?> | Coresuite Business</title>
    <link href="<?php echo asset('assets/vendor/bootstrap/css/bootstrap.min.css'); ?>" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet" referrerpolicy="no-referrer" />
    <link href="https://cdn.datatables.net/v/bs5/dt-1.13.8/datatables.min.css" rel="stylesheet">
    <link href="<?php echo asset('assets/css/custom.css'); ?>" rel="stylesheet">
</head>
<body class="layout-wrapper">
<div id="app" class="d-flex">
<?php if ($flashMessages): ?>
    <script>
        window.CS_INITIAL_FLASHES = <?php echo json_encode($flashMessages, JSON_THROW_ON_ERROR); ?>;
    </script>
<?php endif; ?>
