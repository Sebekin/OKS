<?php /** Профиль: смена пароля, состояние 2FA. */ ?>
<?php if (!empty($msg)): ?><div class="alert success"><?= e($msg) ?></div><?php endif; ?>
<?php if (!empty($err)): ?><div class="alert error"><?= e($err) ?></div><?php endif; ?>

<div class="panel" style="max-width:560px">
  <h3>Учётная запись</h3>
  <table class="grid">
    <tr><td class="muted">ФИО</td><td><?= e($me['full_name']) ?></td></tr>
    <tr><td class="muted">Логин</td><td><?= e($me['login']) ?></td></tr>
    <tr><td class="muted">E-mail</td><td><?= e($me['email'] ?? '') ?></td></tr>
    <tr><td class="muted">Роль</td><td><?= e(Rights::ROLES[$me['role']] ?? $me['role']) ?></td></tr>
    <tr>
      <td class="muted">Двухфакторный вход</td>
      <td>
        <?php if ((int)$me['twofa_enabled'] === 1): ?>
          <span class="badge green">Включена</span>
          <span class="muted">(резервных кодов осталось: <?= (int)$codesLeft ?>)</span>
        <?php else: ?>
          <span class="badge red">Выключена</span>
        <?php endif; ?>
      </td>
    </tr>
  </table>

  <div style="margin-top:12px; display:flex; gap:8px; flex-wrap:wrap">
    <?php if ((int)$me['twofa_enabled'] !== 1): ?>
      <form method="post"><?= Csrf::field() ?><input type="hidden" name="action" value="2fa_start">
        <button class="btn" type="submit">Подключить 2FA</button></form>
    <?php elseif (Rights::can('admin.users')): ?>
      <form method="post" onsubmit="return confirm('Отключить двухфакторную защиту для своей учётной записи?')"><?= Csrf::field() ?>
        <input type="hidden" name="action" value="2fa_off">
        <button class="btn danger small" type="submit">Отключить 2FA (админ)</button></form>
    <?php endif; ?>
  </div>
</div>

<div class="panel" style="max-width:560px">
  <h3>Смена пароля</h3>
  <form method="post">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="password">
    <div class="form-row"><label>Текущий пароль</label><input type="password" name="old_pass" required autocomplete="current-password"></div>
    <div class="form-row"><label>Новый пароль</label><input type="password" name="new_pass" required autocomplete="new-password"></div>
    <button class="btn" type="submit">Сохранить новый пароль</button>
  </form>
</div>
