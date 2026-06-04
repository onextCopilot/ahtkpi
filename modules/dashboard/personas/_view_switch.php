<?php
/**
 * Persona view-switcher bar. Visible only to admin/owner so they can preview
 * any dashboard persona (CEO / AM/BD / Manager / Member) via ?view=.
 *
 * The including page should set $dash_view to its own persona key (e.g.
 * 'am_bd') so the right chip is highlighted. Expects $role, $full_name in scope.
 */
$__is_owner_admin = ((($role ?? '') === 'admin') || (($full_name ?? '') === 'Hyun Cao'));
if ($__is_owner_admin):
    $__views = ['ceo' => '🏢 General', 'am_bd' => '💼 AM/BD', 'manager' => '👥 Phòng ban', 'member' => '🙋 Cá nhân'];
    $__active = (!empty($_GET['view']) && isset($__views[$_GET['view']])) ? $_GET['view'] : ($dash_view ?? 'ceo');
?>
<div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:16px;">
    <span style="font-size:12px; font-weight:600; color:#64748b;">Xem dưới vai trò:</span>
    <?php foreach ($__views as $vk => $vlabel):
        $is_active = ($vk === $__active);
        $href = '/dashboard?view=' . $vk; ?>
        <a href="<?php echo $href; ?>" style="padding:6px 14px; border-radius:99px; font-size:13px; font-weight:600; text-decoration:none; border:1px solid <?php echo $is_active ? '#0f172a' : '#cbd5e1'; ?>; background:<?php echo $is_active ? '#0f172a' : '#fff'; ?>; color:<?php echo $is_active ? '#fff' : '#475569'; ?>;"><?php echo $vlabel; ?></a>
    <?php endforeach; ?>
</div>
<?php endif; ?>
