<?php snippet('header1') ?>

  <main class="main" role="main">

<section class="content blogarticle">
  <article>
    <h1><?php echo $page->title()->html() ?></h1>
   <?php echo $page->date('m/d/Y') ?> |
   <strong><?php echo $page->author()->html() ?></strong>
      <div class="text">
      <p> <?php echo $page->text()->kirbytext() ?></p>

    <a href="<?php echo url('journal') ?>">Back…</a>

  </article>
</section>
  
    

  </main>

<?php snippet('footer') ?>