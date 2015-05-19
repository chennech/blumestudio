<?php snippet('header') ?>

  <main class="main" role="main">

<ul class="rslides" id="slider1">
  <li><img src="content/1-projects/1-project-a/1.jpg" alt=""></li>
  <li><img src="content/1-projects/1-project-a/2.jpg" alt=""></li>
  <li><img src="content/1-projects/1-project-a/3.jpg" alt=""></li>
</ul>
<!--<ul class ="rslides">
        <?php foreach($project->images() as $image): ?>
            <li><img src="<?php echo $image->url() ?>" alt="<?php echo html($image->title()) ?>" ></li>
        <?php endforeach ?>
    </ul>-->
    
     <?php snippet('projects') ?>

  </main>

<?php snippet('footer') ?>

