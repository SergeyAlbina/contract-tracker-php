<?php
declare(strict_types=1);

namespace App\Modules\Admin;

use App\App;
use App\Http\{Request, Response};
use App\Shared\Enum\CaseBlockType;

final class AdminController
{
    public function __construct(private readonly App $app)
    {
    }

    public function index(Request $request): Response
    {
        $pdo = $this->app->pdo();

        $caseCounts = [];
        foreach ($pdo->query('SELECT block_type, COUNT(*) AS cnt FROM cases GROUP BY block_type') as $row) {
            $caseCounts[(string) $row['block_type']] = (int) $row['cnt'];
        }

        $duplicatesRows = (int) $pdo->query('SELECT COUNT(*) FROM cases WHERE duplicate_of_case_id IS NOT NULL')->fetchColumn();
        $duplicateBundles = (int) $pdo->query(
            "SELECT COUNT(*) FROM (
                SELECT bundle_key
                FROM cases
                WHERE bundle_key IS NOT NULL AND bundle_key <> ''
                GROUP BY bundle_key
                HAVING COUNT(*) > 1
            ) t"
        )->fetchColumn();

        return $this->app->view('admin/index', [
            'title' => 'Администрирование',
            'usersTotal' => (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
            'usersActive' => (int) $pdo->query('SELECT COUNT(*) FROM users WHERE is_active = 1')->fetchColumn(),
            'contractsTotal' => (int) $pdo->query('SELECT COUNT(*) FROM contracts')->fetchColumn(),
            'procurementsTotal' => (int) $pdo->query('SELECT COUNT(*) FROM procurements')->fetchColumn(),
            'casesTotal' => (int) $pdo->query('SELECT COUNT(*) FROM cases')->fetchColumn(),
            'duplicatesRows' => $duplicatesRows,
            'duplicateBundles' => $duplicateBundles,
            'caseCounts' => $caseCounts,
            'blockTypes' => CaseBlockType::cases(),
        ]);
    }
}
