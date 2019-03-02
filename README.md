Views iCal

# Installation

Install just as you would any module. Use composer: 

$ composer require drupal/views_ical

# Set up

To create an iCal feed. 

1, Have a content type prepared to use for a feed. A content type should have fields that provide the following: Title, authored date, start date, end date, unique id, summary. For full reference, see the Internet Calendaring and Scheduling Core Object Specification RFC https://tools.ietf.org/html/rfc5545
2, Create a view.
3, Add a "Feed" display mode.
4, Set the format to "iCal Feed"
5. Set "Show" to "iCal Fields" and un-check the "Provide default field wrapper elements" option, which was selected by default. 
6. Add all the necessary fields. There is a lot of flexibility in how fields are defined here. Refer to the RFC https://tools.ietf.org/html/rfc5545 and subsequent updates, for a complete list of allowed fields. Use the "Label" option to set the field as defined in the RFC. A basic set of field labels will include the following:

DTSTAMP
DTSTART
DTEND
SUMMARY
UID

For date fields, use the "Custom" formatter and set the format to "Ymd\THis\Z". This format should also use the UTC timezone override as it has the "Z" at the end. 

Un-check the "place colon after label" and "Link to content" options in each field's settings. 
