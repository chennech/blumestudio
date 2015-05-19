<section class="content blog">

 <!-- <h1><?php echo $page->title()->html() ?></h1>
  <?php echo $page->text()->kirbytext() ?> 
  
  
 
  
  -->

<ul class="teaser cf row">

  <?php foreach($page->children()->visible()->flip() as $article): ?>
<li class="column third">


  <article>
	
	
	 <?php
	  	$selectedImage = $article->thumbimage();
	  	?>
	  
	<?php if($image = $article->images()->find((string)$selectedImage)): ?>
	  
	  <a href="<?php echo $article->url() ?>">
	    <img src="<?php echo $image->url() ?>" alt="<?php echo $article->title()->html() ?>" >
	  </a>
	  <?php endif ?>
    <h1><?php echo $article->title()->html() ?></h1>
    <?php echo $article->date('m/d/Y') ?> |
    <strong><?php echo $article->author()->html() ?></strong>
    <p class="text"><?php echo $article->text()->excerpt(300) ?></p>
    <a href="<?php echo $article->url() ?>">Read more…</a>
    
  </article>

   </li>
   <?php endforeach ?>
 </ul>



<?php

				$list       = $page->children()->paginate(3);
				$pagination = $list->pagination();

			?>
</section>
