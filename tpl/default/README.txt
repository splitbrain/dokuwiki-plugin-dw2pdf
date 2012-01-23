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

  * ''header_odd.html'' -- Header for odd pages
  * ''header_even.html'' -- Header for even pages
  * ''header_first.html'' -- Header for the first page
  * ''header.html'' -- Header for all pages
  * ''footer_odd.html'' -- Footer for odd pages
  * ''footer_even.html'' -- Footer for even pages
  * ''footer_first.html'' -- Footer for the first page
  * ''footer.html'' -- Footer for all pages
  * ''citation.html'' -- Citationbox to be printed after each article

You can use all HTML that is understood by mpdf
(See http://mpdf1.com/manual/index.php?tid=256)

If you reference image files, be sure to prefix them with the @TPLBASE@
parameter (See [[#Replacements]] below).

===== Replacements =====

The following replacement patterns can be used within the header and
footer files.

  * ''@PAGE@'' -- current page number in the PDF
  * ''@PAGES@'' -- number of all pages in the PDF
  * ''@ID@'' -- The article's pageID
  * ''@TITLE@'' -- The article's title
  * ''@PAGEURL@'' -- URL to the article
  * ''@WIKI@'' -- The wiki's title
  * ''@WIKIURL@'' -- URL to the wiki
  * ''@UPDATE@'' -- Time of the last update of the article
  * ''@DATE@'' -- time when the PDF was created (might be in the past if cached)
  * ''@BASE@'' -- the wiki base directory
  * ''@TPLBASE@'' -- the PDF template base directory (use to reference images)
  * ''@QRCODE@'' -- QR code image pointing to the original page url

===== Styles =====

Custom stylings can be provided in the following file:

  * style.css

You can use all the CSS that is understood by mpdf
(See http://mpdf1.com/manual/index.php?tid=34)

