<?php snippet('header') ?>

  <main class="main" role="main">

  <?php foreach($page->children() as $gallery): ?>
  
  <div class="gallery">
  
    <h2><?php echo $gallery->title() ?></h2>
  
    <ul>
      <?php foreach($gallery->images() as $image): ?>
      <li>
        <a rel="<?php echo $gallery->uid() ?>" href="<?php echo $image->url() ?>"><?php echo thumb($image, array('width' => 200, 'height' => 200, 'crop' => true)) ?></a>
      </li>
      <?php endforeach ?>
    </ul>
  
  </div>
  
  <?php endforeach ?>
  


  </main>

<?php snippet('footer') ?>