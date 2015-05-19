<section class="content blog">

 <!-- <h1><?php echo $page->title()->html() ?></h1>
  <?php echo $page->text()->kirbytext() ?>-->

  <?php foreach($page->children()->visible()->flip() as $article): ?>

  <article>
    <h1><?php echo $article->title()->html() ?></h1>
    <?php echo $article->date('m/d/Y') ?> |
    <strong><?php echo $article->author()->html() ?></strong>
    <p><?php echo $article->text()->excerpt(300) ?></p>
    <a href="<?php echo $article->url() ?>">Read more…</a>
  </article>

  <?php endforeach ?>



<?php

				$list       = $page->children()->paginate(3);
				$pagination = $list->pagination();

			?>
</section>