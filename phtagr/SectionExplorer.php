<?php

include_once("$phtagr_lib/SectionBase.php");
include_once("$phtagr_lib/Search.php");
include_once("$phtagr_lib/Edit.php");
include_once("$phtagr_lib/Image.php");
include_once("$phtagr_lib/Database.php");

/** Explore the images.
  @class SectionExplorer */
class SectionExplorer extends SectionBase
{

function SectionExplorer()
{
  $this->name="explorer";
  $this->_search=new Search();
  $this->_search->from_url();
  $this->_tags=array();
  $this->_sets=array();
  $this->_locs=array();
}

function get_search()
{
  return $this->_search;
}

/** @return Returns an hash of tags from the displayed images of the current 
 * page.  The hash key is the tag itself and the hash value is the number of 
 * occurences of the tag */
function get_tags()
{
  return $this->_tags();
}

/** @return Returns an hash of sets from the displayed images of the current 
 * page.  The hash key is the set itself and the hash value is the number of 
 * occurences of the set */
function get_sets()
{
  return $this->_sets();
}

/** @return Returns an hash of locations from the displayed images of the 
 * current page.  The hash key is the location itself and the hash value is 
 * the number of occurences of the location */
function get_locations()
{
  return $this->_locs();
}


/** Print the page navigation bar. It prints the first, the current and the last pagest. Also a preview and a next page link. 
  @param search Search object for the naviagator
  @param current Index of current page 
  @param count Absolut count of pages*/
function print_navigator($search, $current, $count)
{
  if ($count<2) return;

  echo "<div class=\"navigator\">";
  
  if ($current>0)
  {
    $i=$current-1;
    $search->set_page_num($i);
    $url=$search->get_url();
    echo "<a href=\"$url\">"._("Prev")."</a>&nbsp;\n";
  }
    
  for ($i=0; $i < $count; $i++)
  {
    if ($i == $current)
    {
      echo "&nbsp;<span class=\"current\">$i</span>&nbsp;\n";
    }
    else if ($i != $current && 
            ($count <= 10) ||
            ($i < 3 || $i > $count-4 || 
            ($i-$current < 4 && $current-$i<4)))
    {
      $search->set_page_num($i);
      $url=$search->get_url();
      printf("<a href=\"$url\">%d</a>\n",$i);
    }
    else if ($i == $count-4 || $i == 3) 
      echo "&nbsp;...&nbsp;\n";
  }
  if ($current<$count-1)
  {
    $i=$current+1;
    $search->set_page_num($i);
    $url=$search->get_url();
    echo "&nbsp;<a href=\"$url\">"._("Next")."</a>\n";
  }
  echo "</div>\n\n";
}

/** Merge an array and count the entries
  @param counter Pointer to the merged array
  @param add Array to add */
function _array_count_merge(&$counter, $add)
{
  foreach ($add as $value)
    $counter[$value]=$counter[$value]+1;
}

/** Collects the meta data from the image like tags, sets and locations. These 
 * can be accessed by get_tags(), get_sets() and get_locations() 
 * @param image Image object */
function collect_meta($image)
{
  if (!$image)
     return;

  $this->_array_count_merge(&$this->_tags, $image->get_tags());
  $this->_array_count_merge(&$this->_sets, $image->get_sets());
  $this->_array_count_merge(&$this->_locs, $image->get_locations());
}

/** Print the current page with an table */
function print_content()
{
  global $db;
  global $user; 

  $search=$this->get_search();
  $sql=$search->get_num_query();
  // for debugging
  //$this->comment($sql);
  $result = $db->query($sql);
  if (!$result)
  {
    return;
  }
  $row=mysql_fetch_row($result);
  $count=$row[0];
  
  if (count($search->tags)>0)
  {
    echo "<h2>Explore Tag: ";
    foreach ($search->tags as $tag)
      echo "$tag ";
    echo "</h2>\n";
  }
  else
    echo "<h2>"._("Explore Tags")."</h2>\n";

  if ($count==0)
  {
    echo "<p>"._("No images found!")."</p>\n";
    return;
  } else {
    $num_pages=floor($count / $search->get_page_size());
    if ($num_pages<$search->get_page_num())
      $search->set_page_num($num_pages);
  }

  $sql=$search->get_query();
  //$this->comment($sql);
  $result = $db->query($sql);
  if (!$result)
  {
    return;
  }

  // Formular for further actions
  echo "<form id=\"formExplorer\" action=\"index.php\" method=\"post\">\n";
  
  $nav_search=clone $search;
  $nav_current=$nav_search->get_page_num();
  $nav_size=$nav_search->get_page_size();
  $nav_search->set_page_num(0);
  $nav_search->set_pos(0);
  $this->print_navigator($nav_search, $nav_current, ceil($count/$nav_size));
 
  echo "<div class=\"images\">\n";
  $cell=0;
  $pos=$search->get_page_size()*$search->get_page_num();
  while($row = mysql_fetch_row($result)) 
  {
    echo "<div class=\"thumbcell\"><a name=\"img-".$row[0]."\"/>\n";
    $search->set_pos($pos);
    $sec_img=new SectionImage($row[0]);
    if ($cell==0)
      $sec_img->print_js_groups();
    $sec_img->print_preview(&$search);
    $this->collect_meta($sec_img->get_image());
    echo "</div>\n";
    $cell++;
    if ($cell%2==0)
      echo "<div class=\"row2\" ></div>\n";
    if ($cell%3==0)
      echo "<div class=\"row3\" ></div>\n";
    if ($cell%4==0)
      echo "<div class=\"row4\" ></div>\n";
    $pos++;
  }

  echo "</div>\n\n";

  $this->print_navigator($nav_search, $nav_current, ceil($count/$nav_size));

  echo "<div class=\"edit\">";
  echo $search->get_form();

  $edit=new Edit();
  $edit->print_bar();
  if ($user->is_member()||$user->is_guest())
    $edit->print_edit_inputs();
  $edit->print_buttons();
  echo "</div>\n";
  echo "</form>\n";

  global $bulb;
  if (isset($bulb))
    $bulb->set_data($this->_tags, $this->_sets, $this->_locs);
}

}

?>
