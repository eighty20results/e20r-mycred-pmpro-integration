=== Eighty / 20 Results: Integrate myCred and Paid Memberships Pro ===
Contributors: sjolshagen
Tags: mycred, pmpro, paid memberships pro, members, memberships, integration
Requires at least: 4.0
Tested up to: 4.7
Stable tag: 1.2.2

Assign myCred points for Paid Memberships Pro member actions/activities

== Description ==

Administrator can configure myCred points for recurring membership payment renewals.

== Installation ==

1. Upload the `e20r-mycred-pmpro-integration` folder to the `/wp-content/plugins/` directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Edit your membership levels and update the settings in the "Configure myCred" options for each membership level.

== Changelog ==
== 1.2.2 ==

* BUG: Didn't load the settings correctly

== 1.2.1 ==

* ENHANCEMENT/BUG: Fix typo in help text

== 1.2 ==

* ENHANCEMENT: Added pmpro_checkout_confirmed handler, in case it's needed.
* ENHANCEMENT: Help text for the point configuration
* BUG/ENHANCEMENT: Fix variable name for the max point score for the level
* BUG/ENHANCEMENT: Set input type to number for max score/points
* BUG: Didn't wrap the test functionality in the WP_DEBUG requirement
* ENHANCEMENT: Add PHPdoc

== 1.1 ==

* ENHANCEMENT: Adding support for max point limits and 'unlimited' points
* ENHANCEMENT: Filter to define the value for the 'unlimited' points equivalent
* BUG: Didn't include the e20rUtils class path

== 1.0 ==

* Initial release of this myCred / PMPro integration plugin
