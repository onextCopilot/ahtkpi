<?php
$tabs = ['Q1_2026', 'Q2_2026'];
$active_tab = 'Q1_2026';
$search = '';
?>
                <div class="tabs-container">
                    <?php foreach ($tabs as $tab): ?>
                        <a href="?quarter=<?= urlencode($tab) ?><?= $search ? '&search=' . urlencode($search) : '' ?>"
                            class="quarter-tab <?= $active_tab === $tab ? 'active' : '' ?>">
                            <?= str_replace('_', ' ', $tab) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
