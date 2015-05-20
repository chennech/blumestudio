<?php if(!defined('KIRBY')) exit ?>

title: Project

pages: true
files:
  sortable: true
fields:
  title:
    label: Title
    type: text
  subtitle:
    label: SubTitle
    type: text
    icon: font
  text:
    label: Text
    type:  textarea
  year:
      label: Year
      type:  text
  tags:
    label: Tags
    type:  tags
  role:
     label: Role
     type:  tags
  linkname:
     label: LinkText
     type:  text 
  link:
     label: Link
     type:  url 
  collaborators:
     label: collaborators
     type:  text 
 thumbimage:
     label: Thumb Image
     type: select
     options: images
     width: 1/2
     help: Thumbnail Image (width:380px)
 coverimage:
     label: Cover Image
      type: select
      options: images