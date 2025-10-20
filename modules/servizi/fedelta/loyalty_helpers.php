<?php

if (!function_exists('recalculate_loyalty_balances')) {
    /**
     * Recalculate running balance for loyalty movements of a given customer.
     */
    function recalculate_loyalty_balances(PDO $pdo, int $clienteId): void
    {
        $movementsStmt = $pdo->prepare('SELECT id, punti FROM fedelta_movimenti WHERE cliente_id = :cliente_id ORDER BY data_movimento ASC, id ASC');
        $movementsStmt->execute([':cliente_id' => $clienteId]);
        $movements = $movementsStmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$movements) {
            return;
        }

        $runningTotal = 0;
        $updateStmt = $pdo->prepare('UPDATE fedelta_movimenti SET saldo_post_movimento = :saldo WHERE id = :id');

        foreach ($movements as $movement) {
            $runningTotal += (int) $movement['punti'];
            $updateStmt->execute([
                ':saldo' => $runningTotal,
                ':id' => (int) $movement['id'],
            ]);
        }
    }
}

if (!function_exists('current_operator_label')) {
    /**
     * Return the display label for the current operator.
     */
    function current_operator_label(): string
    {
        $displayName = $_SESSION['display_name'] ?? null;
        if ($displayName) {
            return $displayName;
        }

        $username = $_SESSION['username'] ?? null;
        if ($username) {
            return (string) $username;
        }

        $email = $_SESSION['email'] ?? null;
        if ($email) {
            return (string) $email;
        }

        return 'Sistema';
    }
}
