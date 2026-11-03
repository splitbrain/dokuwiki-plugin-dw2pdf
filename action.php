<?php

use dokuwiki\ErrorHandler;
use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\Event;
use dokuwiki\Extension\EventHandler;
use dokuwiki\plugin\dw2pdf\MenuItem;
use dokuwiki\plugin\dw2pdf\src\Cache;
use dokuwiki\plugin\dw2pdf\src\CollectorFactory;
use dokuwiki\plugin\dw2pdf\src\Config;
use dokuwiki\plugin\dw2pdf\src\ExportException;
use dokuwiki\plugin\dw2pdf\src\PdfExportService;
use Mpdf\MpdfException;

/**
 * dw2Pdf Plugin: Conversion from dokuwiki content to pdf.
 *
 * Export html content to pdf, for different url parameter configurations
 * DokuPDF which extends mPDF is used for generating the pdf from html.
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Luigi Micco <l.micco@tiscali.it>
 * @author     Andreas Gohr <andi@splitbrain.org>
 */
class action_plugin_dw2pdf extends ActionPlugin
{
    /**
     * action_plugin_dw2pdf constructor.
     */
    public function __construct()
    {
        global $JSINFO;

        $this->loadConfig();

        $JSINFO['plugins']['dw2pdf']['showexporttemplate'] = $this->getConf('showexporttemplate');

        if ($this->getConf('showexporttemplate')) {
            $templates = [$this->getConf('template')];
            $dir = scandir(DOKU_PLUGIN . 'dw2pdf' . DIRECTORY_SEPARATOR . 'tpl');
            foreach ($dir as $value) {
                if (is_dir(DOKU_PLUGIN . 'dw2pdf' . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . $value) && !in_array($value, array(".", "..", $this->getConf('template')))) {
                    $templates[] = $value;
                }
            }
            $JSINFO['plugins']['dw2pdf']['templates'] = json_encode($templates);
        }
    }
    /**
     * Register the events
     *
     * @param EventHandler $controller
     */
    public function register(EventHandler $controller)
    {
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'convert');
        $controller->register_hook('MENU_ITEMS_ASSEMBLY', 'AFTER', $this, 'addsvgbutton');
    }

    /**
     * Do the HTML to PDF conversion work
     *
     * @param Event $event
     * @throws MpdfException
     */
    public function convert(Event $event)
    {
        global $REV, $DATE_AT, $INPUT;

        // our event?
        $allowedEvents = ['export_pdfbook', 'export_pdf', 'export_pdfns'];
        if (!in_array($event->data, $allowedEvents)) {
            return;
        }

        $this->loadConfig();

        try {
            $config = new Config($this->conf);
            $collector = CollectorFactory::create(
                $event->data,
                $config,
                ((int) $REV) ?: null,
                ((int) $DATE_AT) ?: null
            );
            $cache = new Cache($config, $collector);

            $pdfService = new PdfExportService(
                $config,
                $collector,
                $cache,
                $this->getLang('tocheader'),
                $INPUT->server->str('REMOTE_USER', '', true)
            );

            $cacheFile = $pdfService->getPdf(); // dumps HTML when in debug mode and exits
        } catch (ExportException $e) {
            // expected failure carrying a message meant for the user; escape dynamic parts as
            // the message may end up in an HTML sink (see exportError())
            $args = array_map('hsc', $e->getArgs());
            $this->exportError($event, vsprintf($this->getLang($e->getMessage()), $args));
            return;
        } catch (\Exception $e) {
            // unexpected failure, keep the details out of the user's way but log them
            ErrorHandler::logException($e);
            $this->exportError($event, $this->getLang('exportfailed'));
            return;
        }

        // take over the request and deliver the file
        $event->preventDefault();
        $event->stopPropagation();

        $pdfService->sendPdf($cacheFile); // exits after sending
    }

    /**
     * Surface an export failure to the user
     *
     * BookCreator triggers export_pdfbook as a background download via the jQuery.fileDownload
     * plugin, which cannot be redirected. It reads the response body and injects it into an error
     * dialog through jQuery.html(), so failures for that action are answered with an HTTP error and
     * the message in the body. Any dynamic parts of the message must therefore be HTML escaped by
     * the caller. All other actions are regular navigations and get a flash message plus a redirect
     * back to the current page.
     *
     * @param Event $event The export event being handled
     * @param string $message The localized message to show the user, safe for HTML output
     * @return void
     */
    protected function exportError(Event $event, string $message): void
    {
        if ($event->data === 'export_pdfbook') {
            http_status(400);
            header('Content-Type: text/html; charset=utf-8');
            echo $message;
            exit();
        }

        msg($message, -1);
        $event->data = 'redirect';
    }


    /**
     * Add 'export pdf' button to page tools, new SVG based mechanism
     *
     * @param Event $event
     */
    public function addsvgbutton(Event $event)
    {
        global $INFO;
        if ($event->data['view'] != 'page' || !$this->getConf('showexportbutton')) {
            return;
        }

        if (!$INFO['exists']) {
            return;
        }

        array_splice($event->data['items'], -1, 0, [new MenuItem()]);
    }
}
