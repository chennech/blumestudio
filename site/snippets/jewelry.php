<!--<h2>Latest projects</h2>-->


<ul class="rslides" id="slider2">
  <?php foreach(page('jewelry')->children()->visible()->limit(20) as $project): ?>
  <li>
    <!--<h3><a href="<?php echo $project->url() ?>"><?php echo $project->title()->html() ?></a></h3>
    <p><?php echo $project->text()->excerpt(80) ?> <a href="<?php echo $project->url() ?>">read&nbsp;more&nbsp;→</a></p>-->
    <?php if($image = $project->images()->sortBy('sort', 'asc')->first()): ?>
    <a href="http://www.jewelry.blumestudio.com/">
      <img src="<?php echo $image->url() ?>" alt="<?php echo $project->title()->html() ?>" >
    </a>
    <?php endif ?>
  </li>
  <?php endforeach ?>
</ul>

