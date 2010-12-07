/* opml-browser.js - JavaScript for the opml-browser WordPress plugin */

var opml_browser = {

image_url: '',

addEvent: function(obj, event, listener) {
  if (obj.addEventListener) {
      obj.addEventListener(event, listener, false);
  } else if (obj.attachEvent) {
      obj.attachEvent("on" + event, listener);
  }
},

stopEvent: function(e) {
  if (e && e.stopPropagation) {
    e.stopPropagation();
  }
  else {
    e.cancelBubble = true;
  }
},

showHelp: function(e, help) {
  e = (e) ? e : window.event;
  var posX = e.x;
  var posY = e.y;
  if (help) {
    help.className = 'opml-browser-visible opml-browser-tooltip';
    help.style.left = posX;
    help.style.top = posY+1;
    opml_browser.stopEvent(e);
  }
},

hideHelp: function(e, help) {
  if (help) {
    help.className = 'opml-browser-invisible opml-browser-tooltip';
    opml_browser.stopEvent(e);
  }
},

clickFolder: function(id)
{
  var name = "opml-browser-children" + id;
  var imgname = "opml-browser-folder" + id;
  var el = document.getElementById(name);
  if (el.style.display == 'none')
  {
    document.images[imgname].src = opml_browser.image_url + '/folderopen.png';
    el.style.display = 'block';
  }
  else
  {
    document.images[imgname].src = opml_browser.image_url + '/folder.png';
    el.style.display = 'none';
  }
},

makeHelper: function(tooltip, show) {
  if (show)
    return function(e) { opml_browser.showHelp(e, tooltip); }
  return function(e) { opml_browser.hideHelp(e, tooltip); }
},

makeClick: function(elem, img) {
  var id = elem.id.substr(20);	// 'opml-browser-element' + id
  img.alt = 'Expand/collapse';
  return function() { opml_browser.clickFolder(id); }
}

}

opml_browser.addEvent(window, "load", function() {
  var items = document.getElementsByTagName('div');
  for (var i = 0; i < items.length; i++) 
  {
      if (items[i].className.search(/(^|\s)opml-browser-element($|\s)/) >= 0) {
        var spans = items[i].getElementsByTagName("span");
        var applyto = [];
        var tooltip = null;
        for (var j = 0; j < spans.length; j++) {
	  if (spans[j].parentNode == items[i]) {	// Only immediate child elements
            if (spans[j].className.search(/(^|\s)opml-browser-tooltip($|\s)/) >= 0) {
	      tooltip = spans[j];
            }
	    else {	// Apply tooltip to all contained spans
	      applyto.push(spans[j]);
	      if (spans[j].className.search(/(^|\s)opml-browser-buttondiv($|\s)/) >= 0) {
	        var imgs = spans[j].getElementsByTagName("img");
		for (var k = 0; k < imgs.length; k++) {
		  if (imgs[k].className.search(/(^|\s)opml-browser-category($|\s)/) >= 0) {
		    opml_browser.addEvent(imgs[k], "click", opml_browser.makeClick(items[i], imgs[k]));
		  }
		}
	      }
	    }
	  }
        }
        if (tooltip) {
          for (var j = 0; j < applyto.length; j++) {
	    opml_browser.addEvent(applyto[j], "mouseover", opml_browser.makeHelper(tooltip, true));
	    opml_browser.addEvent(applyto[j], "mouseout", opml_browser.makeHelper(tooltip, false));
	  }
        }
      }
  }
});
