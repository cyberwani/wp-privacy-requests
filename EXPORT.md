# How to Connect Your Plugin to Core's New Personal Data Exporter

## Background

In WordPress 4.9.5, new tools were added to make compliance with laws like the
European Union's General Data Protection Regulation, or GDPR for short. Among
the tools added is a Personal Data Export tool which supports exporting all
the personal data for a given user in a ZIP file.

In addition to the personal data stored in things like WordPress comments,
plugins can also hook into the exporter feature to export the personal
data they collect, whether it be in something like postmeta or even an
entirely new Custom Post Type (CPT).

## How It Works

The "key" for all the exports is the user's email address - this was chosen
because it supports exporting personal data for both full-fledged registered
users and also unregistered users (e.g. like a logged out commenter).

However, since assembling a personal data export could be an intensive
process and will likely contain sensitive data, we don't want to just
generate it and email it to the requestor without confirming the request, so
the admin-facing starts all requests by having the admin enter the username
or email address making the request and then sends then a link to click
to confirm their request.

A list of requests and whether they have been confirmed is available to
the administrator in the same user interface.  Once a request has been
confirmed, the admin can generate and download or directly email the
personal data export ZIP file for the user.

Inside the ZIP file the user receives, they will find a "mini website"
with an index HTML page containing their personal data organized in
groups (e.g. a group for comments, etc. )

## Design Internals

Whether the admin downloads the personal data export ZIP file or sends
it directly to the requestor, the way the personal data export is
assembled is identical - and relies on hooking "exporter" callbacks to
do the dirty work of collecting all the data for the export.

When the admin clicks on the download or email link, an AJAX loop begins
that iterates over all the exporters registered in the system, one at a time.
In addition to exporters built into core, plugins can register their own
exporter callbacks.

The exporter callback interface is designed to be as simple as possible.
A exporter callback receives the email address we are working with,
and a page parameter as well. The page parameter (which starts at 1) is
used to avoid plugins potentially causing timeouts by attempting to export
all the personal data they've collected at once.

The exporter callback replies with whatever data it has for that
email address and page and whether it is done or not. If a exporter
callback reports that it is not done, it will be called again (in a
separate request) with the page parameter incremented by 1.

Exporter callbacks are expected to return an array of items for the
export. Each item contains an a group identifier for the group of which
the item is a part (e.g. comments, posts, orders, etc.), an optional group
label (translated), an item identifier (e.g. comment-133) and then an array of
name, value pairs containing the data to be exported for that item.

It is noteworthy that the value could be a media path, in which case the
media file will be added to the exported ZIP file with a link in the
"mini website" "index" HTML document to it.

When all the exporters have been called to completion, core first assembles
an "index" HTML document that serves as the heart of the export report.
First, it walks the aggregate data and finds all the groups that core
and plugins have identified.

Then, for each group, it walks the data and finds all the items, using
their item identifier to collect all the data for a given
item (e.g. comment-133) from all the exporters into a single entry for the
report.  That way, core and plugins can all contribute data for the same
item (e.g. a plugin may add location information to comments) and in the
final export, all the data for a given item (e.g. comment 133) will be
presented together.

All of this is rendered into the HTML document and then the HTML document
is zipped with any media attachments before being returned to the
admin or emailed to the user. Exports are cached on the server for 1 day and
then deleted.

## What to Do

A plugin can register one or more exporters, but most plugins will only
need one. Let's work from the example given above where a plugin adds
location data for the commenter to comments.

First, let's assume the plugin has used `add_comment_meta` to add location
data using `$meta_key` of `location`

The first thing the plugin needs to do is to create an exporter function
that accepts an email address and a page, e.g.:

```
function my_plugin_exporter( $email_address, $page = 1 ) {
  $number = 500; // Limit us to avoid timing out
  $page = (int) $page;

  $export_items = array();

  $comments = get_comments(
 	  array(
 	    'author_email' => $email_address,
 	    'number'       => $number,
 	    'paged'        => $page,
 	    'order_by'     => 'comment_ID',
 	    'order'        => 'ASC',
 	    )
 	);

  foreach ( (array) $comments as $comment ) {
    $location = get_comment_meta( $comment->comment_ID, 'location', true );

    // Only add location data to the export if it is not empty
    if ( ! empty( $location ) ) {
      // Most item IDs should look like postType-postID
      // If you don't have a post, comment or other ID to work with,
      // use a unique value to avoid having this item's export
      // combined in the final report with other items of the same id
      $item_id = "comment-{$comment->comment_ID}";

      // Core group IDs include 'comments', 'posts', etc.
      // But you can add your own group IDs as needed
      $group_id = 'comments';

      // Optional group label. Core provides these for core groups.
      // If you define your own group, the first exporter to
      // include a label will be used as the group label in the
      // final exported report
      $group_label = __( 'Comments' );

      // This plugin only has one item to offer, but plugins
      // can add as many items in the item data array as they want
      $data = array(
        'name'  => __( 'Commenter Location' ),
        'value' => $location
      );

      $export_items[] = array(
   	    'group_id'    => $group_id,
   	    'group_label' => $group_label,
   	    'item_id'     => $item_id,
   	    'data'        => $data,
   	    );
    }
  }

  // Tell core if we have more comments to work on still
  $done = count( $comments ) < $number;

  return array(
    'data' => $export_items,
    'done' => $done,
  );
}
```

The next thing the plugin needs to do is to register the callback by
filtering the exporter array using the `wp_privacy_personal_data_exporters`
filter.

When registering you provide a friendly name for the export (to aid in
debugging - this friendly name is not shown to anyone at this time)
and the callback, e.g.

```
function register_my_plugin_exporter( $exporters ) {
  $exporters[] = array(
    'exporter_friendly_name' => __( 'Comment Location Plugin' ),
    'callback'               => 'my_plugin_exporter',
    );
  return $exporters;
}

add_filter(
  'wp_privacy_personal_data_exporters',
  'register_my_plugin_exporter',
  10
);
```

And that's all there is to it! Your plugin will now provide data
for the export!
