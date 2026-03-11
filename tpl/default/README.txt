====== dw2pdf Templates ======

Templates define the design of the created PDF files and are a good way
to easily customize them to your Corporate Identity.

To create a new template, just create a new folder within the plugin's
''tpl'' folder and put your header, footers and style definitions in it.

===== Headers and Footers =====

The following files can be created and will be used to set headers and
footers on odd or even pages. Special headers/footers can be used on the
first page of a document. If a file is does not exist the next more generic
one will be tried. Eg. if You don't differ between even and odd pages,
just the header.html is used.

  * ''header.html'' -- Header for all pages
  * ''header_odd.html'' -- Header for odd pages
  * ''header_even.html'' -- Header for even pages
  * ''header_first.html'' -- Header for the first page

  * ''footer.html'' -- Footer for all pages
  * ''footer_odd.html'' -- Footer for odd pages
  * ''footer_even.html'' -- Footer for even pages
  * ''footer_first.html'' -- Footer for the first page

  * ''citation.html'' -- Citationbox to be printed after each article
  * ''cover.html'' -- Added once before first page
  * ''back.html'' -- Added once after last page

You can use all HTML that is understood by mpdf
(See http://mpdf1.com/manual/index.php?tid=256)

If you reference image files, be sure to prefix them with the @TPLBASE@
parameter (See [[#Replacements]] below).

===== Replacements =====

The following replacement patterns can be used within the header and
footer files.

  * ''@PAGE@'' -- current page number in the PDF
  * ''@PAGES@'' -- number of all pages in the PDF
  * ''@TITLE@'' -- the article's title
  * ''@WIKI@'' -- the wiki's title
  * ''@WIKIURL@'' -- URL to the wiki
  * ''@DATE@'' -- time when the PDF was created (might be in the past if cached)
  * ''@BASE@'' -- the wiki base directory
  * ''@INC@'' -- the absolute wiki install directory on the filesystem
  * ''@TPLBASE@'' -- the PDF template base directory (use to reference images)
  * ''@TPLINC@'' -- the absolute path to the PDF template directory on the filesystem
  * ''@DATE(<date>[, <format>])@'' -- formats the given date with [[config:dformat]] or with the given format such as ''%Y-%m-%e'', e.g. this would give just the current year ''@DATE(@DATE@,%Y)@''
  * ''@USERNAME@'' -- name of the user who creates the PDF

//Remark about Bookcreator//:
The page depended replacements are only for ''citation.html'' updated for every page.
In the headers and footers the ID of the bookmanager page of the Bookcreator is applied.
  * ''@ID@'' -- The article's pageID
  * ''@PAGEURL@'' -- URL to the article
  * ''@UPDATE@'' -- Time of the last update of the article
  * ''@QRCODE@'' -- QR code image pointing to the original page url (requires an online generator, see config setting)

===== Revisions Replacements =====

You can use ''@OLDREVISIONS@'' to display page changelog. Custom HTML can be provided
by using ''@OLDREVISIONS("<html>")@''. You can display the first X revisions by using
''@OLDREVISIONS("<html>",<first>)@'', where ''<first>'' can also be a negative value.

The following replacement patterns can be used within the revisions.

  * ''@REVDATE@'' -- date of revision. You can use modifiers to format the revision date, e.g. ''@REVDATE(%m)@'' returns the revision month
  * ''@REVIP@'' -- ip from user name of revision
  * ''@REVTYPE@'' -- type of revision (e.g. "E" [edit], "C" [create])
  * ''@REVID@'' -- id of revision
  * ''@REVUSER@'' -- user name of revision
  * ''@REVSUM@'' -- summary of revision
  * ''@REVEXTRA@'' -- revision extra data
  * ''@REVSIZECHANGE@'' -- size change of revision

===== Styles =====

Custom stylings can be provided in the following file of your dw2pdf-template folder:

  * style.css

The custom PDF selector ''@page last-page :first'' allows you to customize the CSS of the last PDF page.

You can use all the CSS that is understood by mpdf
(See http://mpdf1.com/manual/index.php?tid=34)

===== Fonts =====

You can use custom fonts with your template by creating file ''fonts.php'' within your template folder. E.g.:

<code>
<?php
return [
    'frutiger' => [
            'R' => 'Frutiger-Normal.ttf',
            'I' => 'FrutigerObl-Normal.ttf',
    ]
];
</code>

Copy the font files to the ''fonts/'' subfolder within your template folder.

You can use them on your template by using CSS style ''font-family: asap'', where "asap" is the name of the font on your ''fonts.php'' file.
