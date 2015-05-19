<?php if(!defined('KIRBY')) exit ?>

title: Journalarticle
thumbimage:
    label: Thumb Image
    type: select
    options: images
    width: 1/2
    help: Thumbnail Image (width:380px)
pages: true
files:
  sortable: true
fields:
  title:
    label: Title
    type:  text
  text:
    label: Text
    type:  textarea
  year:
      label: Year
      type:  text
  tags:
    label: Tags
    type:  tags
 
          