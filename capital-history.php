<?php
require __DIR__ . '/app_state.php';

$pageTitle = 'Capital / Puhunan';
$activePage = 'capital';
$capitalError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['capital_action'])) {
    $description = trim((string) ($_POST['description'] ?? ''));
    $amount = (float) ($_POST['amount'] ?? 0);
    if ($description === '' || $amount <= 0) {
        $capitalError = 'Enter a description and an amount greater than zero.';
    } else {
        groceria_record_capital_transaction($description, $amount);
    }
}

$transactions = groceria_capital_transactions();
$initialCapital = groceria_initial_capital();
$spentCapital = groceria_capital_spent();
$remainingCapital = groceria_remaining_capital();
$runningBalance = $initialCapital;
include __DIR__ . '/partials/header.php';
?>
<section class="page-heading">
    <div>
        <span class="eyebrow">Money trail</span>
        <h1>Capital / Puhunan</h1>
        <p>See where the starting capital went and why the remaining balance changed.</p>
    </div>
    <div class="capital-balance-callout"><span>Remaining capital</span><strong><?= groceria_format_money($remainingCapital) ?></strong></div>
</section>
<section class="capital-summary" aria-label="Capital summary">
    <article><span>Initial capital</span><strong><?= groceria_format_money($initialCapital) ?></strong><small>Starting puhunan</small></article>
    <article><span>Total spent</span><strong class="capital-spent"><?= groceria_format_money($spentCapital) ?></strong><small>Inventory and expenses</small></article>
    <article class="capital-remaining"><span>Remaining capital</span><strong><?= groceria_format_money($remainingCapital) ?></strong><small><?= number_format(($remainingCapital / $initialCapital) * 100, 0) ?>% of starting capital</small></article>
</section>
<section class="capital-layout">
    <article class="panel table-panel">
        <div class="panel-heading">
            <div><span class="eyebrow">Capital history</span><h2>Every deduction from puhunan</h2></div>
        </div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>Date</th><th>Description</th><th>Amount spent</th><th>Remaining balance</th><th>Recorded by</th></tr></thead>
                <tbody>
                    <?php foreach ($transactions as $transaction): ?>
                        <?php $runningBalance -= (float) $transaction['amount']; ?>
                        <tr>
                            <td><?= htmlspecialchars((string) $transaction['date']) ?></td>
                            <td class="strong"><?= htmlspecialchars((string) $transaction['description']) ?></td>
                            <td class="capital-spent">-<?= groceria_format_money((float) $transaction['amount']) ?></td>
                            <td class="strong"><?= groceria_format_money($runningBalance) ?></td>
                            <td><?= htmlspecialchars((string) $transaction['recorded_by']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>
    <aside class="panel capital-form-panel">
        <div class="panel-heading"><div><span class="eyebrow">Record spending</span><h2>Add deduction</h2></div></div>
        <?php if ($capitalError !== ''): ?><p class="form-error"><?= htmlspecialchars($capitalError) ?></p><?php endif; ?>
        <form method="post" class="capital-form">
            <input type="hidden" name="capital_action" value="add_transaction">
            <label>Description<input type="text" name="description" placeholder="e.g. Utility expense" required></label>
            <label>Amount spent<input type="number" name="amount" min="0.01" step="0.01" placeholder="0.00" required></label>
            <button type="submit" class="button button-primary">Record expense</button>
        </form>
    </aside>
</section>
<?php include __DIR__ . '/partials/footer.php'; ?>
