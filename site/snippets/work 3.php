<!--<h2>Latest projects</h2>-->


<?php
  $projects = $site->index()->filterBy('template', 'projects')->first()->id();
	$project = $pages->find($projects);
	$tags = tagcloud($project);
?>

<ul class="filteroptions option-set md-show caps center">

	<li><a href="" data-filter="*" class="active"><?php echo l::get('filter.all') ?></a></li>

 	<?php foreach($tags as $tag): ?>
		<li><a href="" data-filter=".<?php echo $tag->name() ?>"><?php echo $tag->name() ?></a></li>
	<?php endforeach ?>

</ul>

<div id="portfolio" >
<?php if(param('tag')) {

	$projects = $pages->find($project)
						->children()
						->visible()
						->filterBy('tags', param('tag'), ',')
						->limit(15);

	} else {

	$projects = $pages->find($project)
						->children()
						->visible()
						->limit(15);

	} ?>



  <?php foreach(page('projects')->children()->visible()->limit(30) as $project): ?>
 <div class="box mx-auto overflow-hidden <?php foreach(str::split($project->tags()) as $tag): ?><?php echo $tag ?> <?php endforeach ?>">
    
    
    <a href="<?php echo $project->url() ?>">
    <?php
    	$selectedImage = $project->thumbimage();
    	?>
    
  <!--  <p><?php echo $project->text()->excerpt(80) ?> <a href="<?php echo $project->url() ?>">read&nbsp;more&nbsp;→</a></p>-->
    <?php if($image = $project->images()->find((string)$selectedImage)): ?>
    
      <img src="<?php echo $image->url() ?>" alt="<?php echo $project->title()->html() ?>" >
   
    <?php endif ?>
    
    <h4 class="font2"><a href="<?php echo $project->url() ?>"><?php echo $project->title()->html() ?><!--</a>--></h4>
    
   </a>
  <?php endforeach ?>
  </div>

</div>

