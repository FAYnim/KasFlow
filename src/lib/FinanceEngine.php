<?php
/**
 * FinanceEngine - Centralized Money Tracker & Financial Ledger Engine
 * Single Source of Truth for Accounts, Categories, Transactions, and Balances.
 */
class FinanceEngine
{
    /**
     * Get list of accounts along with their real-time calculated balances.
     */
    public static function getAccountsWithBalances(PDO $pdo, bool $onlyActive = false): array
    {
        $sql = "
            SELECT 
                a.id,
                a.name,
                a.type,
                a.icon,
                a.initial_balance,
                a.is_active,
                a.sort_order,
                a.created_at,
                COALESCE(t_in.total_in, 0) AS total_income,
                COALESCE(t_out.total_out, 0) AS total_expense,
                COALESCE(tr_in.total_tr_in, 0) AS total_transfer_in,
                COALESCE(tr_out.total_tr_out, 0) AS total_transfer_out,
                (
                    a.initial_balance 
                    + COALESCE(t_in.total_in, 0) 
                    - COALESCE(t_out.total_out, 0) 
                    + COALESCE(tr_in.total_tr_in, 0) 
                    - COALESCE(tr_out.total_tr_out, 0)
                ) AS balance
            FROM accounts a
            LEFT JOIN (
                SELECT account_id, SUM(amount) AS total_in 
                FROM transactions 
                WHERE type = 'income' 
                GROUP BY account_id
            ) t_in ON t_in.account_id = a.id
            LEFT JOIN (
                SELECT account_id, SUM(amount) AS total_out 
                FROM transactions 
                WHERE type = 'expense' 
                GROUP BY account_id
            ) t_out ON t_out.account_id = a.id
            LEFT JOIN (
                SELECT to_account_id, SUM(amount) AS total_tr_in 
                FROM transactions 
                WHERE type = 'transfer' 
                GROUP BY to_account_id
            ) tr_in ON tr_in.to_account_id = a.id
            LEFT JOIN (
                SELECT account_id, SUM(amount) AS total_tr_out 
                FROM transactions 
                WHERE type = 'transfer' 
                GROUP BY account_id
            ) tr_out ON tr_out.account_id = a.id
        ";

        if ($onlyActive) {
            $sql .= " WHERE a.is_active = 1 ";
        }

        $sql .= " ORDER BY a.sort_order ASC, a.name ASC";

        $stmt = $pdo->query($sql);
        $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($accounts as &$acc) {
            $acc['id'] = (int)$acc['id'];
            $acc['initial_balance'] = (float)$acc['initial_balance'];
            $acc['is_active'] = (int)$acc['is_active'];
            $acc['sort_order'] = (int)$acc['sort_order'];
            $acc['total_income'] = (float)$acc['total_income'];
            $acc['total_expense'] = (float)$acc['total_expense'];
            $acc['total_transfer_in'] = (float)$acc['total_transfer_in'];
            $acc['total_transfer_out'] = (float)$acc['total_transfer_out'];
            $acc['balance'] = (float)$acc['balance'];
        }

        return $accounts;
    }

    /**
     * Get real-time balance of a specific account.
     */
    public static function getAccountBalance(PDO $pdo, int $accountId): float
    {
        $accounts = self::getAccountsWithBalances($pdo, false);
        foreach ($accounts as $a) {
            if ((int)$a['id'] === $accountId) {
                return (float)$a['balance'];
            }
        }
        return 0.0;
    }

    /**
     * Get list of categories.
     */
    public static function getCategories(PDO $pdo, ?string $type = null, bool $onlyActive = false): array
    {
        $sql = "SELECT * FROM categories WHERE 1=1";
        $params = [];

        if ($type !== null && in_array($type, ['income', 'expense'], true)) {
            $sql .= " AND (type = ? OR type = 'both')";
            $params[] = $type;
        }

        if ($onlyActive) {
            $sql .= " AND is_active = 1";
        }

        $sql .= " ORDER BY name ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($categories as &$cat) {
            $cat['id'] = (int)$cat['id'];
            $cat['is_active'] = (int)$cat['is_active'];
        }

        return $categories;
    }

    /**
     * Get comprehensive financial summary.
     */
    public static function getSummary(PDO $pdo): array
    {
        $accounts = self::getAccountsWithBalances($pdo, true);
        $totalBalance = 0.0;
        foreach ($accounts as $acc) {
            $totalBalance += (float)$acc['balance'];
        }

        $incomeStmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE type = 'income'");
        $totalIncome = (float)$incomeStmt->fetchColumn();

        $expenseStmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE type = 'expense'");
        $totalExpense = (float)$expenseStmt->fetchColumn();

        $queueStmt = $pdo->query("SELECT COALESCE(SUM(nominal), 0) FROM kas_mingguan_queue WHERE status = 'pending'");
        $pendingQueueNominal = (float)$queueStmt->fetchColumn();

        $queueCountStmt = $pdo->query("SELECT COUNT(*) FROM kas_mingguan_queue WHERE status = 'pending'");
        $pendingQueueCount = (int)$queueCountStmt->fetchColumn();

        return [
            'total_balance'         => $totalBalance,
            'total_saldo'           => $totalBalance,
            'total_income'          => $totalIncome,
            'total_expense'         => $totalExpense,
            'total_pending_queue'   => $pendingQueueNominal,
            'pending_queue_nominal' => $pendingQueueNominal,
            'pending_queue_count'   => $pendingQueueCount,
            'account_count'         => count($accounts),
            'accounts'              => $accounts,
        ];
    }

    /**
     * Add a new transaction (income, expense, or transfer).
     */
    public static function addTransaction(PDO $pdo, array $data): int
    {
        $date        = $data['date'] ?? date('Y-m-d');
        $type        = $data['type'] ?? '';
        $accountId   = (int)($data['account_id'] ?? 0);
        $toAccountId = isset($data['to_account_id']) && $data['to_account_id'] !== '' ? (int)$data['to_account_id'] : null;
        $categoryId  = isset($data['category_id']) && $data['category_id'] !== '' ? (int)$data['category_id'] : null;
        $amount      = (float)($data['amount'] ?? 0);
        $desc        = trim($data['description'] ?? '');
        $refType     = $data['ref_type'] ?? 'manual';
        $refId       = isset($data['ref_id']) ? (int)$data['ref_id'] : null;
        $createdBy   = $data['created_by'] ?? null;

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new InvalidArgumentException("Format tanggal harus YYYY-MM-DD");
        }
        if (!in_array($type, ['income', 'expense', 'transfer'], true)) {
            throw new InvalidArgumentException("Tipe transaksi harus income, expense, atau transfer");
        }
        if ($amount <= 0) {
            throw new InvalidArgumentException("Nominal harus lebih dari 0");
        }
        if ($accountId <= 0) {
            throw new InvalidArgumentException("Akun harus dipilih");
        }

        // Account existence check
        $accCheck = $pdo->prepare("SELECT id FROM accounts WHERE id = ?");
        $accCheck->execute([$accountId]);
        if (!$accCheck->fetchColumn()) {
            throw new InvalidArgumentException("Akun asal tidak ditemukan");
        }

        if ($type === 'transfer') {
            if (!$toAccountId || $toAccountId === $accountId) {
                throw new InvalidArgumentException("Akun tujuan transfer harus berbeda dengan akun sumber");
            }
            $accCheck->execute([$toAccountId]);
            if (!$accCheck->fetchColumn()) {
                throw new InvalidArgumentException("Akun tujuan tidak ditemukan");
            }
            $categoryId = null; // Transfer does not have category
        } else {
            $toAccountId = null;
            if ($categoryId) {
                $catCheck = $pdo->prepare("SELECT id FROM categories WHERE id = ?");
                $catCheck->execute([$categoryId]);
                if (!$catCheck->fetchColumn()) {
                    $categoryId = null;
                }
            }
        }

        $sql = "INSERT INTO transactions (date, type, account_id, to_account_id, category_id, amount, description, ref_type, ref_id, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $date, $type, $accountId, $toAccountId, $categoryId, $amount, $desc, $refType, $refId, $createdBy
        ]);

        return (int)$pdo->lastInsertId();
    }

    /**
     * Update an existing transaction.
     */
    public static function updateTransaction(PDO $pdo, int $id, array $data): bool
    {
        $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
        $stmt->execute([$id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            throw new RuntimeException("Transaksi tidak ditemukan");
        }

        $date        = $data['date'] ?? $existing['date'];
        $type        = $data['type'] ?? $existing['type'];
        $accountId   = (int)($data['account_id'] ?? $existing['account_id']);
        $toAccountId = array_key_exists('to_account_id', $data) ? ($data['to_account_id'] ? (int)$data['to_account_id'] : null) : $existing['to_account_id'];
        $categoryId  = array_key_exists('category_id', $data) ? ($data['category_id'] ? (int)$data['category_id'] : null) : $existing['category_id'];
        $amount      = (float)($data['amount'] ?? $existing['amount']);
        $desc        = array_key_exists('description', $data) ? trim($data['description']) : $existing['description'];

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new InvalidArgumentException("Format tanggal harus YYYY-MM-DD");
        }
        if (!in_array($type, ['income', 'expense', 'transfer'], true)) {
            throw new InvalidArgumentException("Tipe transaksi invalid");
        }
        if ($amount <= 0) {
            throw new InvalidArgumentException("Nominal harus lebih dari 0");
        }
        if ($type === 'transfer') {
            if (!$toAccountId || $toAccountId === $accountId) {
                throw new InvalidArgumentException("Akun tujuan transfer harus berbeda");
            }
            $categoryId = null;
        } else {
            $toAccountId = null;
        }

        $sql = "UPDATE transactions 
                SET date = ?, type = ?, account_id = ?, to_account_id = ?, category_id = ?, amount = ?, description = ?
                WHERE id = ?";
        return $pdo->prepare($sql)->execute([
            $date, $type, $accountId, $toAccountId, $categoryId, $amount, $desc, $id
        ]);
    }

    /**
     * Delete a transaction.
     */
    public static function deleteTransaction(PDO $pdo, int $id): bool
    {
        // If linked to kas_mingguan_queue, reset the queue status back to pending
        $pdo->prepare("UPDATE kas_mingguan_queue SET status = 'pending', transaction_id = NULL WHERE transaction_id = ?")
            ->execute([$id]);

        $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Query transactions with filters, pagination, and joined accounts/categories.
     */
    public static function getTransactions(PDO $pdo, array $filters = []): array
    {
        $sql = "
            SELECT 
                t.*,
                a.name AS account_name,
                a.icon AS account_icon,
                a.type AS account_type,
                toa.name AS to_account_name,
                toa.icon AS to_account_icon,
                c.name AS category_name,
                c.icon AS category_icon,
                c.color AS category_color
            FROM transactions t
            JOIN accounts a ON a.id = t.account_id
            LEFT JOIN accounts toa ON toa.id = t.to_account_id
            LEFT JOIN categories c ON c.id = t.category_id
            WHERE 1=1
        ";

        $params = [];

        if (!empty($filters['start_date'])) {
            $sql .= " AND t.date >= ?";
            $params[] = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $sql .= " AND t.date <= ?";
            $params[] = $filters['end_date'];
        }
        if (!empty($filters['type']) && in_array($filters['type'], ['income', 'expense', 'transfer'], true)) {
            $sql .= " AND t.type = ?";
            $params[] = $filters['type'];
        }
        if (!empty($filters['account_id'])) {
            $sql .= " AND (t.account_id = ? OR t.to_account_id = ?)";
            $params[] = (int)$filters['account_id'];
            $params[] = (int)$filters['account_id'];
        }
        if (!empty($filters['category_id'])) {
            $sql .= " AND t.category_id = ?";
            $params[] = (int)$filters['category_id'];
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (t.description LIKE ? OR c.name LIKE ? OR a.name LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $sql .= " ORDER BY t.date DESC, t.id DESC";

        if (!empty($filters['limit'])) {
            $limit = (int)$filters['limit'];
            $offset = isset($filters['offset']) ? (int)$filters['offset'] : 0;
            $sql .= " LIMIT $limit OFFSET $offset";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $r['id'] = (int)$r['id'];
            $r['account_id'] = (int)$r['account_id'];
            $r['to_account_id'] = $r['to_account_id'] !== null ? (int)$r['to_account_id'] : null;
            $r['category_id'] = $r['category_id'] !== null ? (int)$r['category_id'] : null;
            $r['amount'] = (float)$r['amount'];
        }

        return $rows;
    }

    /**
     * Count total transactions matching filters (for pagination).
     */
    public static function countTransactions(PDO $pdo, array $filters = []): int
    {
        $sql = "
            SELECT COUNT(*)
            FROM transactions t
            JOIN accounts a ON a.id = t.account_id
            LEFT JOIN categories c ON c.id = t.category_id
            WHERE 1=1
        ";

        $params = [];

        if (!empty($filters['start_date'])) {
            $sql .= " AND t.date >= ?";
            $params[] = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $sql .= " AND t.date <= ?";
            $params[] = $filters['end_date'];
        }
        if (!empty($filters['type']) && in_array($filters['type'], ['income', 'expense', 'transfer'], true)) {
            $sql .= " AND t.type = ?";
            $params[] = $filters['type'];
        }
        if (!empty($filters['account_id'])) {
            $sql .= " AND (t.account_id = ? OR t.to_account_id = ?)";
            $params[] = (int)$filters['account_id'];
            $params[] = (int)$filters['account_id'];
        }
        if (!empty($filters['category_id'])) {
            $sql .= " AND t.category_id = ?";
            $params[] = (int)$filters['category_id'];
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (t.description LIKE ? OR c.name LIKE ? OR a.name LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Get queue of uncategorized weekly cash items.
     */
    public static function getKasQueue(PDO $pdo, ?string $status = null): array
    {
        $sql = "SELECT * FROM kas_mingguan_queue";
        $params = [];
        if ($status !== null) {
            $sql .= " WHERE status = ?";
            $params[] = $status;
        }
        $sql .= " ORDER BY id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $r['id'] = (int)$r['id'];
            $r['tahun'] = (int)$r['tahun'];
            $r['nominal'] = (float)$r['nominal'];
            $r['transaction_id'] = $r['transaction_id'] !== null ? (int)$r['transaction_id'] : null;
            if (!empty($r['detail'])) {
                $r['detail'] = json_decode($r['detail'], true);
            }
        }

        return $rows;
    }

    /**
     * Claim / record an item from kas_mingguan_queue into transactions.
     */
    public static function claimKasQueue(PDO $pdo, int $queueId, int $accountId, int $categoryId, string $adminUser, ?string $customDesc = null): int
    {
        $stmt = $pdo->prepare("SELECT * FROM kas_mingguan_queue WHERE id = ? FOR UPDATE");
        $stmt->execute([$queueId]);
        $queue = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$queue) {
            throw new RuntimeException("Item antrean kas tidak ditemukan");
        }
        if ($queue['status'] !== 'pending') {
            throw new RuntimeException("Item antrean sudah pernah dicatat sebelumnya");
        }

        $nominal = (float)$queue['nominal'];
        $date = date('Y-m-d');
        $desc = $customDesc ?: $queue['keterangan'];

        if ($nominal >= 0) {
            $type = 'income';
            $amount = $nominal;
        } else {
            // Negative nominal indicates cancellation / deduction correction
            $type = 'expense';
            $amount = abs($nominal);
            if (!$customDesc) {
                $desc = "Koreksi: " . $queue['keterangan'];
            }
        }

        $txId = self::addTransaction($pdo, [
            'date'        => $date,
            'type'        => $type,
            'account_id'  => $accountId,
            'category_id' => $categoryId,
            'amount'      => $amount,
            'description' => $desc,
            'ref_type'    => 'kas_mingguan',
            'ref_id'      => $queueId,
            'created_by'  => $adminUser,
        ]);

        $pdo->prepare("UPDATE kas_mingguan_queue SET status = 'recorded', transaction_id = ? WHERE id = ?")
            ->execute([$txId, $queueId]);

        return $txId;
    }
}
