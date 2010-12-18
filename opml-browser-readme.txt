Plugin Name: opml-browser
Plugin URI: http://www.chipstips.com/?tag=phpopmlbrowse
Description: Display an OPML browser as a widget, in your template, or in a page/post.
Author: Sterling "Chip" Camden
Version: 2.4
Author URI: http://chipsquips.com

Bitbucket repository: http://bitbucket.org/sterlingcamden/opml-browser

This WordPress plugin creates a hierarchical browser for an OPML file that can be hosted
anywhere.  It provides a sidebar widget, as well as an API for displaying the browser in
PHP code.

REQUIREMENTS

You must be using WordPress 2.0 (at least) and have the ability to upload
widgets.  Your host must enable allow_url_fopen if you use a URL to open the file (see 
http://us2.php.net/manual/en/ref.filesystem.php#ini.allow-url-fopen).
Your OPML file must reside on a host that can be accessed from your site.

INSTALLATION

1.  Upload the opml-browser folder to the /wp-content/plugins directory.
2.  In the WordPress dashboard, select "Plugins" and activate the "opml-browser"
    widget plugin.

UPGRADING

1.  Remove the widget from your sidebar and deactivate the plugin before uploading.  Delete the old
    opml-browser.php file from /wp-content/plugins/widgets.
2.  Continue with INSTALLATION.
3.  If upgrading from prior to version 1.2, the options for the single widget should be
    automatically copied to the "opml-browser 1" widget.

FOR WIDGET DISPLAY:

4.  Now go to "Presentation/Sidebar Widgets".  At the bottom, select the number of
    opml-browser widgets you would like to display.
5.  For each opml-broswer widget, open its options.
    a.  Widget Title: set to whatever you want.  I suggest "Blogroll".
    b.  OPML URL: provide the full URL for the OPML file.
    c.  Local Path: if the OPML file resides on the same server as your blog, specify
	local path to the file here to improve performance and reduce bandwidth.
	Leave empty to load from the URL.
    d.  Image URL: the URL for the icon images for each item.  if not set, defaults to
        get_settings('siteurl') . '/wp-content/plugins/opml-browser/images/'.
    e.  Use rss.png from /wp-includes/images (Monda option)?  Check this box to make
        an exception for the location of rss.png to share the image loaded with the
	one used by WordPress itself.
    f.  Exclude if no HTML link?  Check this box to omit any outline entry that
        has no "htmlUrl" or "url" attribute.
    g.  Exclude if no feed link?  Check this box to omit any outline entry that
        has no "xmlUrl" attribute.
    h.  Exclude (this domain)? Check this box to omit any entry that has an htmlURL or
	xmlURL attribute that specifies a URL on the same domain as your WordPress blog.
    i.  Link to OPML? Check this box to provide a link to your OPML using the OPML icon.
	This link will appear as the top-level entry in the hierarchy.
    j.  Show clickable folders for categories?  Check tis box to show a folder icon beside
        each category entry, which can be clicked to open or close the category.
    k.  Start with folders closed? Check this box to have all categories collapsed on
	startup if and only if the "Show clickable folders" option is selected and javascript
	is enabled in the browser (otherwise the user wouldn't have any way to open them).
    l.	Sort items?  Check this box to sort items by title within each folder, in natural order,
        case-insensitive.
    m.  Flatten hierarchy?  Check this box to eliminate category folders.  If sort is enabled,
        then all items will be sorted together.
    n.  Include OPML descriptions as tooltips?  Check this box to use the OPML description
        attribute (if present) as tooltip text for each item.
    o.	Left indent (CSS margin) Use any CSS measurement specification to indicate how
	much sub-items should be indented from their containing item.  It is initially
	set to "5px".  Empty this field	to suppress indentation.
    p.  Include "Get this widget" link (please)?  Check this box to include a link to the
        site for this widget, or uncheck it to omit the link.
6.  Drag the opml-blogroll widget to the sidebar where you want it to appear.
7.  Save changes.

FOR DISPLAY FROM PHP:

	$browser = new OpmlBrowser();
	$browser->name = (string);		// Unique name for this instance (will be filtered for legal div id name chars)
	$browser->filename = (string);		// name of local OPML file or URL
	$browser->opmlurl = (string);		// URL for the OPML (only if you want to link it)
	$browser->image_url = (string);		// URL for images (only to override the default, must have trailing /)
	$browser->monda = (boolean);		// Use rss.png from /wp-includes/images?
	$browser->require_html = (boolean);	// Exclude non-category items with no htmlURL?
	$browser->require_feed = (boolean);	// Exclude non-category items with no xmlUrl?
	$browser->exclude_self = (boolean);	// Exclude htmlUrl or xmlUrl from the blog's domain?
	$browser->show_folders = (boolean);	// Show clickable folders for categories?
	$browser->closeall = (boolean);		// Start with all folders closed?
	$browser->sort = (boolean);		// Sort items?
	$browser->flatten = (boolean);		// Flatten hierarchy?
	$browser->tooltips = (boolean)		// Include OPML descriptions as tooltips? (default = true)
	$browser->margin = (string);		// CSS measurement for indent margin
	$browser->credit = (boolean);		// Include "Get this widget" link? (default = true)
	echo $browser->render();		// Renders the browser

FOR DISPLAY WITHIN THE TEXT OF A POST OR PAGE:

	[opml-browser name="string" filename="string", opmlurl="string", imageurl="string" monda="1"
	        link_opml="1" require_html="1", require_feed="1" exclude_self="1" show_folders="0"
		closeall="1" sort="1" flatten="1" tooltips="0" margin="string" credit="0" ]

	All of the attributes are optional.  In cases where "0" is specified above (show_folders, tooltips,
	credits), omitting the attribute defaults to "1".  If "imageurl" is not specified, the default will
	be used (see above).  All other attributes default to null.  That means, for instance, that you must
	specify link_opml="1" to get an OPML link, and you must specify either filename or opmlurl to serve
	up any links at all.

	You really only need to specify a unique name for each browser if you have more than one.  Widgets use "-widget-N-",
	where N is the number of the widget.  If you don't specify a name, '' is assumed.

OPERATION

To determine the text of an outline item, first the "text" attribute is queried.
If that is not found, the "title" attribute will be used, if specified.  If the OPML
file is linked, any <title> in the <head> section will be used for the text of that
entry.

If an entry has an "xmlUrl" attribute, an icon will be displayed, with a link
to that entry's XML.  The "type" attribute of the entry will be used to find a PNG
file in the image URL.  So, if type="rss", then rss.png will be used.  If type="opml",
opml.png will be used.  If the type attribute specifies a type for which there is no image,
then unknown.png will be used.  To save page load time, you can make an exception for rss.png
and simply use the one from /wp-includes/images by enabling the "Monda option" (named after
the user who suggested it, László Monda).

If the "show clickable folders" option is enabled, and the outline entry contains entries
(typically a category) that have not been flattened by the "flatten hieracrhy" option,
then an open folder icon ("folderopen.png") will be displayed.  This icon can be clicked
to execute javascript that toggles the visibility of the contained entries, as well as
toggling the icon to a closed folder ("folder.png").  Naturally, if javascript is disabled,
the folders are all open all the time.

The text for the entry will be displayed.  If an "htmlUrl" attribute was found, the
text will be linked to that URL.

If the "Include OPML descriptions as tooltips" option is enabled and the OPML item has a
"description" attribute, its value will be used as a tooltip for the item.  The tooltip
becomes visible one second after mouseover, and disappears immediately upon mouseout.

The entire section will be cached using WordPress' built-in caching functions for
up to 15 minutes.  However, if you "Save changes" to the widget in the dashboard,
the cache will be released immediately.

Each widget automatically adds an OPML auto-discovery link to the <head> section of
your page if you have specified a URL.  Thanks to Sergio Longoni (http://kromeblog.kromeboy.net)

CHANGE LOG

VERSION 2.4

- Added the "Monda option" to override the location of rss.png to /wp-includes/images.

VERSION 2.3

- Added option for "Show clickable folders for categories", with a default value of true for
  compatibility with earlier versions.
- Escape any special HTML characters found in the text, title, description, xmlUrl, or htmlUrl attributes,
  but without double-escaping.
- Changed img tags to use the "id" attribute instead of the obsolete "name" attribute.

VERSION 2.2

- Added option for 'Image URL', defaulting to the previous hard-coded location (images subdirectory).
- Added option for 'Include OPML descriptions as tooltips' with a default value of true.
- Added delay of one second before showing tooltips.
- Added option for 'Include "Get this widget" link?' with a default value of true.

VERSION 2.1

- Fixed a parsing error that occurred when outline elements in the OPML were nested more than two
  levels deep.  This required implementing a more robust XML parser than preg_match.
- If a category also provides a feed, both the feed icon and the folder icon are now displayed.

VERSION 2.0

- The plugin now expects to be installed in its own folder: (siteurl)/wp-content/plugins/opml-browser
- JavaScript and CSS have been separated into their own files.
- JavaScript events are now hooked on window load instead of in the HTML.
- Added "alt" attribute to all "img" tags.
- Images are now looked for in (siteurl)/wp-content/plugins/opml-browser/images
- If an image cannot be found, it will be replaced by the supplied unknown.png (a question mark).
- Added "Sort items?" option.
- Added the "Flatten hierarchy?" option.
- The OPML description attribute is now displayed as a tooltip (as in the OPML Blogroll widget).

VERSION 1.3

- Added missing quotes around a couple of href's and a style attribute.
- Corrected the name of the containing div.
- Added end anchor tag for the "Get this widget" link.

VERSION 1.2

- You may now have up to 9 opml-browser widgets. When upgrading, the options from your
  existing opml-browser widget are copied to the first opml-browser widget.
- The option to exclude your domain now ignores differences in case when comparing domains.
- You can now override the title for the OPML link by entering text into the "OPML title override" field.
- Added the ability to embed the browser within a page or post.
- Added a "Get this widget" link at the bottom of the widget.

VERSION 1.1

- Added the "Start with folders closed" option.
- Completely reworked the code, using a class.

STYLING

The widget is displayed inside a <div> section with id of "opml-browser-box".

Whether in the widget or in PHP code, the browser display structure looks like the following
(where N is the name property followed by a counter to make each id unique).  I've excluded some markup to keep it simple.

<div class="opml-browser-element" id="opml-browser-elementN">
  <span class="opml-browser-buttondiv">
    <img name="opml-browser-buttonN" class="opml-browser-button opml-browser-category"/>
  </span>
  <span class="opml-browser-text opml-browser-category">
    Text of the category entry
  </span>
  <div class="opml-browser-children" id="opml-browser-childrenN">
    <div class="opml-browser-element" id="opml-browser-element(N+1)">
      <span class="opml-browser-buttondiv">
    	<img name="opml-browser-button(N+1)" class="opml-browser-button opml-browser-item"/>
      </span>
      <span class="opml-browser-text opml-browser-item">
	Text of the single item
      </span>
    </div>
    ...
  </div>
</div>
...

The name property for a widget will be "-widget-X-", where X is the widget's number from 1 to 9.  Thus, the first element for the
first widget will have a div id of "opml-browser-element-widget-1-1".

Naturally, categories may also contain categories.

There are a number of effects you can therefore achieve in your stylesheet:

1. To suppress the folder icons and thereby also the expand/collapse functionality:

img.opml-browser-category {
        display:none;
}

2. To make category headings bold:

span.opml-browser-category {
        font-weight: bold;
}

3. To reduce the size of all text:

span.opml-browser-text {
        font-size: 80%
}

4. To limit the height of the widget and make it scrollable (doesn't work in IE):

div#opml-browser-box {
        overflow: scroll;
        max-height: 300px;
}


LIMITATIONS

1.  OPML Inclusion is not currently supported.

Send any problems or suggestions to me from the contact form at http://www.chipstips.com/?page_id=3,
or visit any blog post at http://chipstips.com/?tag=phpopmlbrowse and leave a comment.
