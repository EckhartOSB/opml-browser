<?php
class xmlElement {
    var $parent;
    var $tagName;
    var $attributes;
    var $children;
    var $data;

    function xmlElement($tag, $attrs, $parent) {
        $this->parent =& $parent;
	$this->tagName = $tag;
	$this->attributes =& $attrs;
	$this->children = array();
	$this->data = '';
    }

    function find_tag($tagName) {
    	foreach ($this->children as $child) {
	    if ($child->tagName == $tagName) {
	        return $child;
	    }
	    $elem = $child->find_tag($tagName);
	    if ($elem !== false) {
	        return $elem;
	    }
	}
	return false;
    }
}

class xmlDOM {
    var $root;
    var $context;

    function xmlDOM($data) {
	$this->root = new xmlElement('', null, null);
    	$this->context =& $this->root;
	$parser = xml_parser_create();
	xml_set_object($parser, $this);
	xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, false);
	xml_set_element_handler($parser, 'tag_open', 'tag_close');
	xml_set_character_data_handler($parser, "cdata");
	xml_parse($parser, $data);
	xml_parser_free($parser);
    }

    function find_tag($tagName, $from = null) {
    	if (is_null($from))
	    return $this->root->find_tag($tagName);
	return $from->find_tag($tagName);
    }

    function tag_open($parser, $tag, $attributes) {
	$elem =  new xmlElement($tag, $attributes, $this->context);
    	$this->context->children[] =& $elem;
	$this->context =& $elem;
    }

    function tag_close($parser, $tag) {
    	$this->context =& $this->context->parent;
    }

    function cdata($parser, $cdata) {
    	$this->context->data = $cdata;
    }
}
?>
