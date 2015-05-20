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
  text1:
    label: Text column 1
    type:  textarea
  text2:
   label: Text column 2
   type:  textarea
  text3:
    label: Text column 3
    type:  textarea
  mentions:
    label: Mentions
    type:  textarea
  disciplines:
     label: Disciplines
     type:  textarea
  collaborators:
     label: Collaborators
     type:  textarea     
  coverimage:
     label: Cover Image
     type: select
     options: images  