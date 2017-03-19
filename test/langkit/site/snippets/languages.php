<nav class="languages" role="navigation">
  <ul class="languages-menu">
    <?php foreach($site->languages() as $language): ?>
    <li class="languages-item<?php e($site->language() == $language, ' is-active') ?>">
      <a href="<?= $page->url($language->code()) ?>"><?= str::upper($language->code()) ?></a>
    </li>
    <?php endforeach ?>
  </ul>
</nav>
