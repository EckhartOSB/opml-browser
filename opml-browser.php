<?php
/*
Plugin Name: opml-browser widget
Plugin URI: http://www.chipstips.com/?tag=phpopmlbrowse
Description: Widget for browsing an OPML URL or file.
Author: Sterling "Chip" Camden
Version: 2.3
Author URI: http://www.chipsquips.com
*/

require (dirname(__FILE__) . DIRECTORY_SEPARATOR . 'xmlparse.php');

define(DOMAIN_FILTER, '/^.*?:\/\/(www\.)*([^:\/?]*)/');
define(FIX_IDENTIFIER, '/[^a-zA-Z0-9\-]/');

// Parse an XML attribute list for a specific attribute and return its value
function parse_attribute_value($text, $attribute)
{
    preg_match('/(^|[\n\s])' . $attribute . '="(.*?)"/s', $text, $matches);
    return $matches[2];   // Second subpattern is the value, if any.
}

class OpmlBrowser
{
  var $filename;        // Local filename or URL for OPML file
  var $opmlurl;         // URL for OPML
  var $opmltitle;       // Override OPML title
  var $require_feed;    // Exclude if no feed?
  var $require_html;    // Exclude if no html?
  var $exclude_self;    // Exclude this domain?
  var $show_folders;	// Clickable folders for categories
  var $closeall;        // Start with folders closed (if javascript enabled)
  var $sort_items;	// Sort items?
  var $flatten;		// Flatten categories?
  var $tooltips;	// Show tooltips?
  var $margin;          // CSS margin for indent
  var $credit;		// Include credit link?
  var $name;            // Unique name for this instance
  
  var $siteurl;         // URL for WordPress site
  var $host;            // Host name (sans www)
  var $image_url;       // URL for images in the widget
  var $nextid;          // Next identifier for an element div
  var $folders;         // Array of folder div ids
  
  // Class to implement an OPML browser
  
  function OpmlBrowser()
  {
    $this->siteurl = get_settings('siteurl');
    preg_match(DOMAIN_FILTER, $this->siteurl, $this->host);
    $this->host = $this->host[2];
    $this->image_url = $this->siteurl . '/wp-content/plugins/opml-browser/images/';
    $this->nextid = 0;
    $this->name = '';
    $this->folders = array();
    
    $this->filename = null;
    $this->opmlurl = null;
    $this->opmltitle = '';
    $this->require_feed = false;
    $this->require_html = false;
    $this->exclude_self = false;
    $this->show_folders = true;
    $this->closeall = false;
    $this->sort_items = false;
    $this->flatten = false;
    $this->tooltips = true;
    $this->margin = '5px';
    $this->credit = true;
    $this->imagepath = array();
  }

  // Construct an image link, checking to make sure that the image exists.  Otherwise, link to unknown.png instead.
  function image_link($imagename) {
    if (is_null($this->imagepath[$imagename]))
    {
    	$path = $this->image_url . $imagename . '.png';
	if (wp_remote_fopen($path)) {
	  $this->imagepath[$imagename] = $path;
	}
	else {
	  $this->imagepath[$imagename] = $this->image_url . 'unknown.png';
	}
    }
    return $this->imagepath[$imagename];
  }
  
  // Construct a <div> section for an OPML outline element in the browser
  function element_div($text='', $xmlUrl='', $htmlUrl='', $type='rss', $tooltip=null, $hasChildren = false)
  {
    $this->nextid++;    // Get a unique ID
    $folder = $this->name . $this->nextid;
    $display_content = '<div class="opml-browser-element" id="opml-browser-element' . $folder .'" >';
    $display_content .= '<span class="opml-browser-buttondiv">';
    if (!is_null($xmlUrl) && ($xmlUrl != ''))       // Use a feed icon if we have an XML source for this entry
        $display_content .= '<a href="' . htmlspecialchars($xmlUrl, ENT_COMPAT, "UTF-8", false) . '"><img id="opml-browser-button' . $folder . '" class="opml-browser-button opml-browser-item" src="' . $this->image_link($type) .'" alt="Subscribe" /></a>';
    if ($hasChildren && $this->show_folders)                               // A folder icon for categories
    {
      $display_content .= '<img id="opml-browser-folder' . $folder . '" class="opml-browser-button opml-browser-category" src="' . $this->image_link('folderopen'). '" alt="Category" />';
      $this->folders[] = $folder;           // Save to the list of folders for the close_all() method
    }
    $display_content .= '</span><span class="opml-browser-text';  // Common class for all elements for styling
    if ($hasChildren)
      $display_content .= ' opml-browser-category';   // Class for category entries to allow custom styling
    else
      $display_content .= ' opml-browser-item';         // And a different one for line items
    $display_content .= '" >';
    if (!is_null($htmlUrl) && ($htmlUrl != ''))
      $display_content .= '<a href="' . htmlspecialchars($htmlUrl, ENT_COMPAT, "UTF-8", false) . '">';    // Link the HTML
    $display_content .= htmlspecialchars($text, ENT_COMPAT, "UTF-8", false);
    if (!is_null($htmlUrl) && ($htmlUrl != ''))
      $display_content .= '</a>';
    $display_content .= '</span>';
    if (isset($tooltip)) {
      $display_content .= '<span class="opml-browser-tooltip opml-browser-invisible">' .
        htmlspecialchars($tooltip, ENT_COMPAT, "UTF-8", false) . '</span>';
    }
    return $display_content;
  }

  // Construct a <div> section containing all of the child elements for the current id
  function child_div()
  {
    
    $display_content = '<div class="opml-browser-children" id="opml-browser-children' . $this->name . $this->nextid;
    if ($this->margin != '')
      $display_content .= '" style="margin-left:' . $this->margin . ';';    // Indent with a margin
    $display_content .= '">';
    return $display_content;
  }
  
  // Close all folders in the outline
  // We use javascript to do this, so that if the browser does not support javascript they will remain open
  // (since the user couldn't reopen them)
  function close_all()
  {
    $display_content = '<script type="text/javascript">';
    foreach ($this->folders as $folder)
      $display_content .= "opml_browser.clickFolder('" . $folder . "');";
    $display_content .= '</script>';
    return $display_content;
  }
  
  // Collect the OPML outline elements
  function collect_elements($elems, &$elements) {
    foreach ($elems as $elem) {
      if ($elem->tagName == 'outline') {
          $title = $elem->attributes['text'];
          if (is_null($title) || ($title == ''))
              $title= $elem->attributes['title'];
          $htmlUrl = $elem->attributes['htmlUrl'];
          $xmlUrl = $elem->attributes['xmlUrl'];
          $type = $elem->attributes['type'];
          $description = $elem->attributes['description'];
          $hasChildren = ($elem->find_tag('outline') !== false);
      
          $include = true;        // Now process exclusions.  We'll assume that we include this one
          if ($this->flatten || !$hasChildren)      // Especially if it has child elements
          {
            if (($this->require_feed && !(isset($xmlUrl) && ($xmlUrl != ''))) ||
               ($this->require_html && !(isset($htmlUrl) && ($htmlUrl != ''))))
               $include = false;                 
            else if ($this->exclude_self)
            {
              if (isset($htmlUrl))    // Try a domain match against the HTML URL first
              {
                preg_match(DOMAIN_FILTER, $htmlUrl, $matches);
                if (!is_null($matches[2]) && (strcasecmp($matches[2], $this->host) == 0))
                  $include = false;
              }
              if ($include && isset($xmlUrl)) // Then any feed
              {
                preg_match(DOMAIN_FILTER, $xmlUrl, $matches);
                if (!is_null($matches[2]) && (strcasecmp($matches[2], $this->host) == 0))
                  $include = false;
              }
            }
          }
          
          if ($include) {
      	    $elements[$title] = array('htmlUrl' => $htmlUrl, 'xmlUrl' => $xmlUrl, 'type' => $type,
				'tooltip' => ($this->tooltips ? $description : null),
				'hasChildren' => ($hasChildren && !$this->flatten),
				'children' => $elem->children);
          }
          if ($this->flatten && $hasChildren)
            $this->collect_elements($elem->children, $elements);	// Recurse to add children to the end
        }
    }
  }

  // Process a series of outline elements.  Recurses to handle contained elements.
  function outline($elems)
  {
    $elements = array();
    $this->collect_elements($elems, $elements);

    $display_content = '';

    if ($this->sort_items)
    {
      uksort($elements, 'strnatcasecmp');	// Sort keys by natural order, case-insensitive
    }

    foreach ($elements as $title => $settings) {
        $display_content .= $this->element_div($title, $settings['xmlUrl'], $settings['htmlUrl'],
						$settings['type'], $settings['tooltip'], $settings['hasChildren']);
        if ($settings['hasChildren'])   // Recurse to child elements
        {
          $display_content .= $this->child_div();
          $display_content .= $this->outline($settings['children']);
          $display_content .= "</div>";
        }
        $display_content .= "</div>";
    }

    return $display_content;
  }
  
  //  Render an OPML browser
  // This is the main function to call externally.  
  function render()
  {
      $this->folders = array();             // Clear the array of folder ids
      $display_content = '';
      $this->name = preg_replace(FIX_IDENTIFIER, '', $this->name, -1);
      $opml_content = file_get_contents($this->filename);
      if ($opml_content !== false) {
	  $dom = new xmlDOM($opml_content);
          if ($this->opmlurl != '')   // Specifying the opmlurl automatically links to it
          {
            if (($this->opmltitle == '') &&
	        (($title = $dom->find_tag('title')) !== false)) {
                $this->opmltitle = $title->data;   // Use the OPML title element if not specified
            }
            $display_content .= $this->element_div($this->opmltitle, $this->opmlurl, $this->opmlurl, 'opml',
	        null, true);
            $display_content .= $this->child_div();
          }
	  
	  if (($body = $dom->find_tag('body')) !== false) {
              $display_content .= $this->outline($body->children);
	  }
          if ($this->opmlurl != '')
            $display_content .= '</div></div>';
          if ($this->closeall)
            $display_content .= $this->close_all();
      }
      else
      {
        $display_content = "<p>Could not open OPML URL {$this->filename}</p>";
      }
      return $display_content;
  }
  
}   // class OpmlBrowser


// This gets called at the plugins_loaded action
function widget_opml_browser_init() {
	
	// Check for the required API functions
	if ( !function_exists('register_sidebar_widget') || !function_exists('register_widget_control') )
		return;

	// This saves options and prints the widget's config form.
	function widget_opml_browser_control($number) {

        $options = $newoptions = get_option('widget_opml_browser');
        if ( $_POST["opml-browser-submit-$number"] ) {
            $newoptions[$number]['title'] = strip_tags(stripslashes($_POST["opml-browser-title-$number"]));
            $newoptions[$number]['opmlurl'] = $_POST["opml-browser-opmlurl-$number"];
            $newoptions[$number]['opmlpath'] = $_POST["opml-browser-opmlpath-$number"];
            $newoptions[$number]['opmltitle'] = $_POST["opml-browser-opmltitle-$number"];
	    $newoptions[$number]['imageurl'] = $_POST["opml-browser-imageurl-$number"];
            $newoptions[$number]['reqhtml'] = $_POST["opml-browser-reqhtml-$number"];
            $newoptions[$number]['reqfeed'] = $_POST["opml-browser-reqfeed-$number"];
            $newoptions[$number]['noself'] = $_POST["opml-browser-noself-$number"];
            $newoptions[$number]['opmllink'] = $_POST["opml-browser-opmllink-$number"];
            $newoptions[$number]['showfolders'] = $_POST["opml-browser-showfolders-$number"];
            $newoptions[$number]['closeall'] = $_POST["opml-browser-closeall-$number"];
	    $newoptions[$number]['sortitems'] = $_POST["opml-browser-sortitems-$number"];
	    $newoptions[$number]['flatten'] = $_POST["opml-browser-flatten-$number"];
	    $newoptions[$number]['tooltips'] = $_POST["opml-browser-tooltips-$number"];
            $newoptions[$number]['indent'] = $_POST["opml-browser-indent-$number"];
	    $newoptions[$number]['credit'] = $_POST["opml-browser-credit-$number"];
            wp_cache_delete("opml-browser-content-$number");
        }
        if ( $options != $newoptions ) {
            $options = $newoptions;
            update_option('widget_opml_browser', $options);
        }
        $browser = new OpmlBrowser();
        ?>
	<div style="text-align:right">
	  <label for="opml-browser-title-<?php echo $number; ?>" style="display:block">
	    Widget title: <input type="text" id="opml-browser-title-<?php echo $number; ?>" name="opml-browser-title-<?php echo $number; ?>" size="50" value="<?php echo htmlspecialchars($options[$number]['title'], ENT_COMPAT, "UTF-8", false); ?>" />
	  </label>
          <label for="opml-browser-opmlurl-<?php echo $number; ?>" style="display:block">
	    OPML URL: <input type="text" id="opml-browser-opmlurl-<?php echo $number; ?>" name="opml-browser-opmlurl-<?php echo $number; ?>" size="50" value="<?php echo $options[$number]['opmlurl']; ?>" />
	  </label>
          <label for="opml-browser-opmlpath-<?php echo $number; ?>" style="display:block">
	    Local path: <input type="text" id="opml-browser-opmlpath-<?php echo $number; ?>" name="opml-browser-opmlpath-<?php echo $number; ?>" size="50" value="<?php echo $options[$number]['opmlpath']; ?>" />
	  </label>
          <label for="opml-browser-opmltitle-<?php echo $number; ?>" style="display:block">
	    OPML title override: <input type="text" id="opml-browser-opmltitle-<?php echo $number; ?>" name="opml-browser-opmltitle-<?php echo $number; ?>" size="50" value="<?php echo $options[$number]['opmltitle']; ?>" />
	  </label>
          <label for="opml-browser-imageurl-<?php echo $number; ?>" style="display:block">
	    Image URL: <input type="text" id="opml-browser-imageurl-<?php echo $number; ?>" name="opml-browser-imageurl-<?php echo $number; ?>" size="50" value="<?php echo $options[$number]['imageurl']; ?>" />
	  </label>
          <label for="opml-browser-reqhtml-<?php echo $number; ?>" style="display:block">
	    Exclude if no HTML link? <input type="checkbox" id="opml-browser-reqhtml-<?php echo $number; ?>" name="opml-browser-reqhtml-<?php echo $number; ?>" value="1" <?php if ($options[$number]['reqhtml'] == '1') { ?>checked="true"<?php } ?> />
	  </label>
          <label for="opml-browser-reqfeed-<?php echo $number; ?>" style="display:block">
	    Exclude if no feed link? <input type="checkbox" id="opml-browser-reqfeed-<?php echo $number; ?>" name="opml-browser-reqfeed-<?php echo $number; ?>" value="1" <?php if ($options[$number]['reqfeed'] == '1') { ?>checked="true"<?php } ?> />
	  </label>
          <label for="opml-browser-noself-<?php echo $number; ?>" style="display:block">
	    Exclude <?php echo $browser->host;?>? <input type="checkbox" id="opml-browser-noself-<?php echo $number; ?>" name="opml-browser-noself-<?php echo $number; ?>" value="1" <?php if ($options[$number]['noself'] == '1') { ?>checked="true"<?php } ?> />
	  </label>
          <label for="opml-browser-opmllink-<?php echo $number; ?>" style="display:block">
	    Link to OPML? <input type="checkbox" id="opml-browser-opmllink-<?php echo $number; ?>" name="opml-browser-opmllink-<?php echo $number; ?>" value="1" <?php if ($options[$number]['opmllink'] == '1') { ?>checked="true"<?php } ?> />
	  </label>
          <label for="opml-browser-showfolders-<?php echo $number; ?>" style="display:block">
	    Show clickable folders for categories? <input type="checkbox" id="opml-browser-showfolders-<?php echo $number; ?>" name="opml-browser-showfolders-<?php echo $number; ?>" value="1" <?php if ($options[$number]['showfolders'] == '1') { ?>checked="true"<?php } ?> />
	  </label>
          <label for="opml-browser-closeall-<?php echo $number; ?>" style="display:block">
	    Start with folders closed? <input type="checkbox" id="opml-browser-closeall-<?php echo $number; ?>" name="opml-browser-closeall-<?php echo $number; ?>" value="1" <?php if ($options[$number]['closeall'] == '1') { ?>checked="true"<?php } ?> />
	  </label>
          <label for="opml-browser-sortitems-<?php echo $number; ?>" style="display:block">
	    Sort items? <input type="checkbox" id="opml-browser-sortitems-<?php echo $number; ?>" name="opml-browser-sortitems-<?php echo $number; ?>" value="1" <?php if ($options[$number]['sortitems'] == '1') { ?>checked="true"<?php } ?> />
	  </label>
	  <label for="opml-browser-flatten-<?php echo $number; ?>" style="display:block">
	    Flatten hierarchy? <input type="checkbox" id="opml-browser-flatten-<?php echo $number; ?>" name="opml-browser-flatten-<?php echo $number; ?>" value="1" <?php if ($options[$number]['flatten'] == '1') { ?>checked="true"<?php } ?> />
	  </label>
	  <label for="opml-browser-tooltips-<?php echo $number; ?>" style="display:block">
	    Include OPML descriptions as tooltips? <input type="checkbox" id="opml-browser-tooltips-<?php echo $number; ?>" name="opml-browser-tooltips-<?php echo $number; ?>" value="1" <?php if ($options[$number]['tooltips'] == '1') { ?>checked="true"<?php } ?> />
	  </label>
          <label for="opml-browser-indent-<?php echo $number; ?>" style="display:block">
	    Left indent (CSS margin) <input type="text", id="opml-browser-indent-<?php echo $number; ?>" name="opml-browser-indent-<?php echo $number; ?>" size="10" value="<?php echo $options[$number]['indent']; ?>" />
	  </label>
	  <label for="opml-browser-credit-<?php echo $number; ?>" style="display:block">
	    Include &quot;Get this widget&quot; link (please)? <input type="checkbox" id="opml-browser-credit-<?php echo $number; ?>" name="opml-browser-credit-<?php echo $number; ?>" value="1" <?php if ($options[$number]['credit'] == '1') { ?>checked="true"<?php } ?> />
	  </label>
	  <input type="hidden" name="opml-browser-submit-<?php echo $number; ?>" id="opml-browser-submit-<?php echo $number; ?>" value="1" />
	</div>
	    <?php
	}
 

	// This prints the widget
	function widget_opml_browser($args, $number = 1) {
 
    	    extract($args);
	    $defaults = array('title' => 'Blogroll');
	    $options = (array) get_option('widget_opml_browser');
	
	    foreach ( $defaults as $key => $value )
		if ( !isset($options[$number][$key]) )
			$options[$number][$key] = $defaults[$key];
	
	    echo $before_widget;
	    echo $before_title . $options[$number]['title'] . $after_title;
	    ?>

            <div id="opml-browser-box-<?php echo $number; ?>">
            <?php
                 if ($widget_content = wp_cache_get("opml-browser-content-$number")) {
                     echo $widget_content;        // Found it in the cache
                 }
                 else
                 {
                     if (isset($options[$number]['opmlpath']) && ($options[$number]['opmlpath'] != ''))
                       $filename = $options[$number]['opmlpath'];
                     else
                       $filename = $options[$number]['opmlurl'];
                     if (isset($filename) && ($filename != '')) {
                        $browser = new OpmlBrowser();
                        $browser->filename = $filename;
                        if ($options[$number]['opmllink'] == '1')
                        {
                            $browser->opmlurl = $options[$number]['opmlurl'];
                            $browser->opmltitle = $options[$number]['opmltitle'];
                        }
			$imageurl = $options[$number]['imageurl'];
			if (isset($imageurl) && ($imageurl != ''))
			{
			  if (substr_compare($imageurl, '/', -1) != 0)
			  {
			    $imageurl .= '/';
			  }
			  $browser->image_url = $imageurl;
			}
                        $browser->require_html = ($options[$number]['reqhtml'] == '1');
                        $browser->require_feed = ($options[$number]['reqfeed'] == '1');
                        $browser->exclude_self = ($options[$number]['noself'] == '1');
			$browser->show_folders = ($options[$number]['showfolders'] == '1');
                        $browser->closeall = ($options[$number]['closeall'] == '1');
                        $browser->sort_items = ($options[$number]['sortitems'] == '1');
			$browser->flatten = ($options[$number]['flatten'] == '1');
			$browser->tooltips = ($options[$number]['tooltips'] == '1');
                        $browser->margin = $options[$number]['indent'];
			$browser->credit = ($options[$number]['credit'] == '1');
                        $browser->name = "-widget-$number-";
                        $widget_content = $browser->render();
			if ($browser->credit)
			{
			  $widget_content.= '<div id="opml-browser-link-' . $number . '" class="opml-browser-link"><a href="http://chipstips.com/?tag=phpopmlbrowse">Get this widget</a></div>';
			}
                        echo $widget_content;
                        wp_cache_add("opml-browser-content-$number", $widget_content);
                     }
                     else
                     {
                        echo "<p>OPML URL or file not specified</p>";
                     }
                 }
            ?>
            </div>
	    <?php
		echo $after_widget;
	}

    function widget_opml_browser_register()
    {
        // Check for version upgrade
        $options = get_option('widget_opml_browser');
        $need_update = false;
        if (isset($options['version'])) {
            $curver = $options['version'];
            if ($curver < 1.2) {
                $curver = 1.2;
                $options['version'] = $curver;
                $options[1]['title'] = $options['title'];
                $options[1]['opmlurl'] = $options['opmlurl'];
                $options[1]['opmlpath'] = $options['opmlpath'];
                $options[1]['reqhtml'] = $options['reqhtml'];
                $options[1]['reqfeed'] = $options['reqfeed'];
                $options[1]['noself'] = $options['noself'];
                $options[1]['opmllink'] = $options['opmllink'];
                $options[1]['closeall'] = $options['closeall'];
                $options[1]['indent'] = $options['indent'];
                $options['number'] = 1;
                $need_update = true;
            }
            /* No changes to options between 1.2 and 2.2 */
	    if ($curver < 2.2) {
	        $curver = 2.2;
                $options['version'] = $curver;
		for ($i = 1; $i <= $options['number']; $i++)
		{
		    $options[$i]['imageurl'] = get_settings('siteurl') . '/wp-content/plugins/opml-browser/images/';
		    $options[$i]['tooltips'] = '1';
		    $options[$i]['credit'] = '1';
		}
		$need_update = true;
	    }
	    if ($curver < 2.3) {
	        $curver = 2.3;
		$options['version'] = $curver;
		for ($i = 1; $i <= $options['number']; $i++)
		{
		    $options[$i]['showfolders'] = '1';
		}
		$need_update = true;
	    }
        }
        else {
          $curver = 2.3;
          $options['version'] = $curver;
	  $options[1]['imageurl'] = get_settings('siteurl') . '/wp-content/plugins/opml-browser/images/';
	  $options[1]['tooltips'] = '1';
          $options[1]['indent'] = "5px";
	  $options[1]['credit'] = '1';
          $need_update = true;
        }

        $number = $options['number'];
        if ( $number < 1 ) {
            $number = 1;
            $options['number'] = 1;
            $need_update = true;
        }
        else if ( $number > 9 ) {
            $number = 9;
            $options['number'] = 9;
            $need_update = true;
        }

        // Apply any upgrades here by testing $curver and setting $need_update to true

        if ($need_update)
          update_option('widget_opml_browser', $options);

        for ($i = 1; $i <= 9; $i++) {
            $name = array('opml-browser %s', null, $i);
            register_sidebar_widget($name, $i <= $number ? 'widget_opml_browser' : /* unregister */ '', $i);
            register_widget_control($name, $i <= $number ? 'widget_opml_browser_control' : /* unregister */ '', 550, 500, $i);
        }
        add_action('sidebar_admin_setup', 'widget_opml_browser_setup');
        add_action('sidebar_admin_page', 'widget_opml_browser_page');

        // add the Link to the OPML file For Autodiscovery
        add_action('wp_head', 'opml_browser_head');	

        // Add a filter for embedded browsers in content
        add_filter('the_content', 'opml_browser_content_filter');
    }

    function widget_opml_browser_setup() {
        $options = $newoptions = get_option('widget_opml_browser');
        if ( isset($_POST['opml-browser-number-submit']) ) {
            $number = (int) $_POST['opml-browser-number'];
            if ( $number > 9 ) $number = 9;
            else if ( $number < 1 ) $number = 1;
            $newoptions['number'] = $number;
        }
        if ( $options != $newoptions ) {
            $options = $newoptions;
            update_option('widget_opml_browser', $options);
            widget_opml_browser_register();
        }
    }

    function widget_opml_browser_page() {
        $options = $newoptions = get_option('widget_opml_browser');
?>
	<div class="wrap">
		<form method="POST">
			<h2>OPML Browser Widgets</h2>
			<p><?php _e('How many opml-browser widgets would you like?'); ?>
			<select id="opml-browser-number" name="opml-browser-number" value="<?php echo $options['number']; ?>">
<?php for ( $i = 1; $i < 10; ++$i ) echo "<option value='$i' ".($options['number']==$i ? "selected='selected'" : '').">$i</option>"; ?>
			</select>
			<span class="submit"><input type="submit" name="opml-browser-number-submit" id="opml-browser-number-submit" value="<?php _e('Save'); ?>" /></span></p>
		</form>
	</div>
<?php
    }

    function opml_browser_head(){
        $options = (array) get_option('widget_opml_browser');
        $number = $options['number'];
        for ($i = 1; $i <= 9; $i++) {
            $opmlurl = $options[$i]['opmlurl'];
            if (isset($opmlurl) && $opmlurl != '')
                echo ' <link rel="outline" type="text/x-opml" title="OPML" href="'.$opmlurl.'" />';
        }
	$filepath = get_settings('siteurl') . '/wp-content/plugins/opml-browser/';
	$filebase = $filepath . 'opml-browser.';
        // Link our JavaScript
	echo '<script language="javascript" type="text/javascript" src="' . $filebase . 'js"></script>';
	// and our stylesheet
	echo '<link rel="StyleSheet" type="text/css" href="' . $filebase . 'css"/>';
	// Set the image URL for JavaScript
	echo '<script language="javascript" type="text/javascript">opml_browser.image_url = \'' .
		$filepath . 'images\';</script>';
    }

    function opml_browser_content_filter($text) {
        $textarray = preg_split("/(\[opml-browser.*\])/sU", $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $limit = count($textarray);
        $output = '';
        for ($i = 0; $i < $limit; $i++) {
            $content = $textarray[$i];
            if (preg_match("/\[opml-browser(.*)\]/sU", $content, $bcode)) {
                $bcode = $bcode[1];
                $bcode = preg_replace(array('/\&#8221;/','/\&#8243;/'), '"', $bcode, -1);
                $browser = new OpmlBrowser();
                $browser->opmlurl = parse_attribute_value($bcode, "opmlurl");
                $browser->filename = parse_attribute_value($bcode, "filename");
                if (is_null($browser->filename) || $browser->filename == '') {
                    $browser->filename = $browser->opmlurl;
                }
                if (parse_attribute_value($bcode, "link_opml") != "1") {
                    $browser->opmlurl = null;
                }
                $browser->opmltitle = parse_attribute_value($bcode, 'opmltitle');
		$imageurl = parse_attribute_value($bcode, 'imageurl');
		if (isset($imageurl) && ($imageurl != ''))
		{
		  if (substr_compare($imageurl, '/', -1) != 0)
		  {
		    $imageurl .= '/';
		  }
		  $browser->image_url = $imageurl;
		}
                $browser->require_html = (parse_attribute_value($bcode, 'require_html') == '1');
                $browser->require_feed = (parse_attribute_value($bcode, 'require_feed') == '1');
                $browser->exclude_self = (parse_attribute_value($bcode, 'exclude_self') == '1');
                $browser->show_folders = (parse_attribute_value($bcode, 'show_folders') != '0');
                $browser->closeall = (parse_attribute_value($bcode, 'closeall') == '1');
		$browser->sort_items = (parse_attribute_value($bcode, 'sort') == '1');
		$browser->flatten = (parse_attribute_value($bcode, 'flatten') == '1');
		$browser->tooltips = (parse_attribute_value($bcode, 'tooltips') != '0');
                $browser->margin = parse_attribute_value($bcode, 'margin');
		$browser->credit = (parse_attribute_value($bcode, 'credit') != '0');
                $browser->name = parse_attribute_value($bcode, 'name');
                $output .= $browser->render();
            }
            else
                $output .= $content;
        }
        return $output;
    }

    widget_opml_browser_register();
}

// Delay plugin execution to ensure Dynamic Sidebar has a chance to load first
add_action('plugins_loaded', 'widget_opml_browser_init');

?>
