# Multisite CSV Importer
- Tags: multisite, csv, import, batch, spreadsheet, csv
- Version: 0.2.0
- Author: Crystal Barton
- Description: -



Import posts from CSV files into WordPress.



## Description

This plugin imports posts from CSV (Comma Separated Value) files into your
WordPress blog. It can prove extremely useful when you want to import a bunch
of posts from an Excel document or the like - simply export your document into
a CSV file and the plugin will take care of the rest.



## Features

- Imports post title, body, excerpt, tags, date, categories etc.
- Supports custom fields, custom taxonomies and comments
- Deals with Word-style quotes and other non-standard characters using
  WordPress' built-in mechanism (same one that normalizes your input when you
  write your posts)
- Columns in the CSV file can be in any order, provided that they have correct
  headings
- Multilanguage support



## Screenshots

1.  Plugin's interface



## Installation

Installing the plugin:

1.  Unzip the plugin's directory into `wp-content/plugins`.
1.  Activate the plugin through the 'Plugins' menu in WordPress.
1.  The plugin will be available under Tools -> CSV Importer on
    WordPress administration page.



## Usage

Click on the CSV Importer link on your WordPress network admin page, choose the
file you would like to import and click Import. The `examples` directory
inside the plugin's directory contains several files that demonstrate
how to use the plugin. The best way to get started is to import one of
these files and look at the results.

CSV is a tabular format that consists of rows and columns. Each row in
a CSV file represents a post, page, link, or taxonomy terms.

__First Column__

The first column of each row indicates how the row should be processed.

- _h_ : Indicates that the row contains header information (usually the first row of the spreadsheet).
  All rows before the first header row are ignored.
- _#_ : Comments out the row.  This row will be ignored and not processed at all.


### Posts and Pages

__Required Fields__

These four fields are required for all posts and pages.

- _site_ : The slug of the site.
- _type_ : "post" or "page" or a custom post type.
- _action_ : The action to take on the object.  There are a number fo actions that can be taken.  Further details on each of the actions are detailed under the type section.
  - _add_ : Adds a post or page, but does not check for duplicates before
  creation.
  - _update_ : Updates a post or page, if it exists.
  - _replace_ : Replaces a post or page, if it exists, otherwise it creates the 
  post.
  - _prepend_ : Prepends data to a post or page's excerpt and content.
  - _append_ : Appends data to a post or page's excerpt and content.
  - _delete_ : Deletes a post or page.
  - _grep_ : Updates a portion of a post or page using a regex expression and 
  replacement text.
  _Requires the "subject" column._
  Valid subject values: "title", "excerpt", "content", "slug", "guid"
- _title_ : The title of the post or page.

#### Adding Posts or Pages

- _site_ : The slug of the site.
- _type_ : "post" or "page" or custom post type
- _action_ : "add"
  - _add_ : Adds a post or page, but does not check for duplicates before
  creation.
- _title_ : The title of the post or page.
- _excerpt_ : The excerpt of the post or page.
- _content_ : The content of the post or page.
- _date_ : The creation post datetime.
- _author_ : The post author's username.
- _slug_ : The post's slug or name.
- _guid_ : The GUID for the post.
- _parent_ : The title or the parent post (default: no parent).
- _status_ : The post's status .  Valid options are:  "publish", "pending", "draft", "future", "private", "trash".
- _menu-order_ : The int value given to determine the posts order in the menu.
- _password_ : Password for post (default: no password).
- _categories_ : One or more categories seperated by a comma. _Posts only._
- _tags_ : One or more tags seperated by a comma. _Posts only._
- _taxonomy-{taxonomy-slug}_ : One or more custom taxonomy names separated by a comma.

#### Updating and Replacing Posts or Pages

- _site_ : The slug of the site.
- _type_ : "post" or "page" or custom post type
- _action_ : "update" or "replace"
  - _update_ : Updates a post or page, if it exists.
  - _replace_ : Replaces a post or page, if it exists, otherwise it creates the 
  post.
- _title_ : The title of the post or page.
- _excerpt_ : The excerpt of the post or page.
- _content_ : The content of the post or page.
- _date_ : The creation post datetime.
- _author_ : The post author's username.
- _slug_ : The post's slug or name.
- _parent_ : The title or the parent post (default: no parent).
- _status_ : The post's status .  Valid options are:  "publish", "pending", "draft", "future", "private", "trash".
- _menu-order_ : The int value given to determine the posts order in the menu.
- _password_ : Password for post (default: no password).
- _categories_ : One or more categories seperated by a comma. _Posts only._
- _tags_ : One or more tags seperated by a comma. _Posts only._
- _taxonomy-{taxonomy-slug}_ : One or more custom taxonomy names separated by a comma.


#### Prepending and Appending to Posts or Pages

- _site_ : The slug of the site.
- _type_ : "post" or "page" or custom post type
- _action_ : "prepend" or "append"
  - _prepend_ : Prepends data to a post or page's excerpt and content.
  - _append_ : Appends data to a post or page's excerpt and content.
- _title_ : The title of the post or page.
- _excerpt_ : The content to pre- or append to the post excerpt.
- _content_ : The content to pre- or append to the post content.

#### Deleting Posts or Pages

- _site_ : The slug of the site.
- _type_ : "post" or "page" or custom post type
- _action_ : "delete"
  - _delete_ : Deletes a post or page.
- _title_ : The title of the post or page.

#### GREP Posts or Pages

- _site_ : The slug of the site.
- _type_ : "post" or "page" or custom post type
- _action_ : "grep"
  - _grep_ : Updates a portion of a post or page using a regex expression and 
  replacement text.
  Valid subject values: title, excerpt, content, slug, guid
- _title_ : The title of the post or page.
- _subject_ : The subject of the search and replace.  Valid options are: "title", "excerpt", "content", "slug", or "guid".
- _regex_ : The regex to match against the subject.
- _replace-text_ : The text to replace the matched portion of the subject.

#### Add, Updating, or Deleting Taxonomy Terms

- _site_ : The slug of the site.
- _type_ : "post" or custom post type _Page type do not support taxonomies._
- _action_ : "add-taxonomy", "update-taxonomy", "delete-taxonomy"
- _title_ : The title of the post or page.
- _categories_ : One or more categories seperated by a comma.
- _tags_ : One or more tags seperated by a comma.
- _taxonomy-{taxonomy-slug}_ : One or more custom taxonomy names separated by a comma.


### Links

__Required Fields__

These four fields are required for all links.

- _site_ : The slug of the site.
- _type_ : "link"
- _action_ : The action to take on the object.  There are a number fo actions that can be taken.  Further details on each of the actions are detailed under the type section.
  - _add_ : Adds a link, but does not check for duplicates before creation.
  - _update_ : Updates a link, if it exists.
  - _replace_ : Replaces a link, if it exists, otherwise it creates the link.
  - _delete_ : Deletes a link.
  - _grep_ : Updates a portion of a link using a regex expression and 
  replacement text.
  Requires the "subject" column.
  Valid subject values: "name", "url", "description"
- _name_ : The name/title of the link.


#### Adding Links

- _site_ : The slug of the site.
- _type_ : "link"
- _action_ : "add"
  - _add_ : Adds a post or page, but does not check for duplicates before
  creation.
- _name_ : The name/title of the link.
- _name_ : The name/title of the link.
- _url_ : The url for the anchor link.
- _description_ : The description for the link.
- _target_ : The target for the anchor.  Valid options are: "_blank", "_top", "_none".
- _categories_ : One or more categories seperated by a comma.


#### Updating and Replacing Links

- _site_ : The slug of the site.
- _type_ : "link"
- _action_ : "update" or "replace"
  - _update_ : Updates a link, if it exists.
  - _replace_ : Replaces a link, if it exists, otherwise it creates the link.
- _name_ : The name/title of the link.
- _url_ : The url for the anchor link.
- _description_ : The description for the link.
- _target_ : The target for the anchor.  Valid options are: "_blank", "_top", "_none".
- _categories_ : One or more categories seperated by a comma.


#### Deleting Links

- _site_ : The slug of the site.
- _type_ : "link"
- _action_ : "delete"
  - _delete_ : Deletes a link.
- _name_ : The name/title of the link.


#### GREP Links

- _site_ : The slug of the site.
- _type_ : "link"
- _action_ : "grep"
  - _grep_ : Updates a portion of a link using a regex expression and 
    replacement text.
- _name_ : The name/title of the link.
- _subject_ : The subject of the search and replace.  Valid options are: "name", "url", "description".
- _regex_ : The regex to match against the subject.
- _replace-text_ : The text to replace the matched portion of the subject.


### Taxonomies

__Required Fields__

These four fields are required for all posts and pages.

- _site_ : The slug of the site.
- _type_ : "taxonomy"
- _action_ : The action to take.
  - _add_ : Adds the taxonomy terms specified.
  - _delete_ : Deletes the taxonomy terms specified.
- _name_ : The name of the taxonomy.
- _terms_ : The terms to add or delete.




