<?php snippet('header') ?>

  <main class="main" role="main">
    
    <header class="wrap">
      <h1><?= $page->title()->html() ?></h1>
      <div class="intro text">
        <?= $page->intro()->kirbytext() ?>
      </div>
      <hr />
    </header>

    <pre class="wrap"><?php var_dump(l::$data); ?></pre>

    <div class="text wrap">
      <?= $page->text()->kirbytext() ?>
    </div>
  
    <section class="projects-section">
      
      <div class="wrap wide">
        <h2><?= l('home.projects.latest') ?></h2>
        <?php snippet('showcase', ['limit' => 3]) ?>
        <p class="projects-section-more"><a href="<?= page('projects')->url() ?>" class="btn"><?= l('home.projects.more') ?></a></p>
      </div>
      
    </section>

  </main>

<?php snippet('footer') ?>
