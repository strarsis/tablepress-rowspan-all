<?php

// Loads HTML properly with encoding
function load_dom($html) {
  $dom = new DOMDocument('1.0', 'utf-8');
  libxml_use_internal_errors(true); // disable printing parse warnings
  $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8')); // unicode support
  libxml_clear_errors();            // "
  return $dom;
}

// HTML fragment to DOM
// Returns element reference (for passed selector) for fragment
// element->documentElement for containing DOMDocument
function fragment_to_dom($html, $selector) {
  $dom = load_dom($html);

  // Element reference for fragment
  $xpath  = new DOMXpath($dom);
  $result = $xpath->query($selector);
  $el     = $result->item(0);

  $el->documentElement = $dom; // for unknown reasons the element has no documentElement property
  return $el;
}

// DOM to HTML fragment
function dom_to_fragment($el) {
  $tempDom = new DOMDocument('1.0', 'utf-8');
  $tempImported = $tempDom->importNode($el, true);
  $tempDom->appendChild($tempImported);
  $html = $tempDom->saveHTML();
  return $html;
}

// Determines position of child node in parent node
// Returns -1 if node wasn't found in parent - 
// which happens with a root element that is no child of another element.
function dom_parent_position($el) {
  $el_parent   = $el->parentNode;
  $el_siblings = $el_parent->childNodes;
  for($el_sibling_index = 0; $el_sibling_index <= $el_siblings->length; $el_sibling_index++) {
    $el_sibling = $el_siblings->item($el_sibling_index);
    if($el->isSameNode($el_sibling)) {
      return $el_sibling_index;
    }
  }
  return -1;
}

// Changes the tag name of a node
// (http://stackoverflow.com/questions/8163298/how-do-i-change-xml-tag-names-with-php)
function dom_change_tagname( DOMElement $oldTag, $newTagName ) {
    $document = $oldTag->ownerDocument;

    $newTag = $document->createElement($newTagName);
    $oldTag->parentNode->replaceChild($newTag, $oldTag);

    foreach ($oldTag->attributes as $attribute) {
        $newTag->setAttribute($attribute->name, $attribute->value);
    }
    foreach (iterator_to_array($oldTag->childNodes) as $child) {
        $newTag->appendChild($oldTag->removeChild($child));
    }
    return $newTag;
}
