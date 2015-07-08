<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2015 Daniel Garner
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version. 
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace Xibo\Widget;

use Respect\Validation\Validator as v;
use Xibo\Controller\Library;
use Xibo\Factory\DataSetFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Helper\Date;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Helper\Theme;

class Ticker extends Module
{
    /**
     * Install Files
     */
    public function installFiles()
    {
        MediaFactory::createModuleFile('modules/vendor/jquery-1.11.1.min.js')->save();
        MediaFactory::createModuleFile('modules/vendor/moment.js')->save();
        MediaFactory::createModuleFile('modules/vendor/jquery.marquee.min.js')->save();
        MediaFactory::createModuleFile('modules/vendor/jquery-cycle-2.1.6.min.js')->save();
        MediaFactory::createModuleFile('modules/xibo-layout-scaler.js')->save();
        MediaFactory::createModuleFile('modules/xibo-text-render.js')->save();
    }

    /**
     * Loads templates for this module
     */
    public function loadTemplates()
    {
        // Scan the folder for template files
        foreach (glob('modules/ticker/*.template.json') as $template) {
            // Read the contents, json_decode and add to the array
            $this->module->settings['templates'][] = json_decode(file_get_contents($template), true);
        }

        Log::debug(count($this->module->settings['templates']));
    }

    public function validate()
    {
        // Must have a duration
        if ($this->getDuration() == 0)
            throw new \InvalidArgumentException(__('Please enter a duration'));

        $sourceId = $this->getOption('sourceId');

        if ($sourceId == 1) {
            // Feed
            // Validate the URL
            if (!v::url()->notEmpty()->validate($this->getOption('uri')))
                throw new \InvalidArgumentException(__('Please enter a Link for this Ticker'));

        } else if ($sourceId == 2) {
            // DataSet
            // Validate Data Set Selected
            if ($this->getOption('dataSetId') == 0)
                throw new \InvalidArgumentException(__('Please select a DataSet'));

            // Check we have permission to use this DataSetId
            if (!$this->getUser()->checkViewable(DataSetFactory::getById($this->getOption('dataSetId'))))
                throw new \InvalidArgumentException(__('You do not have permission to use that dataset'));

            if ($this->widget->widgetId != 0) {
                // Some extra edit validation
                // Make sure we havent entered a silly value in the filter
                if (strstr($this->getOption('filter'), 'DESC'))
                    throw new \InvalidArgumentException(__('Cannot user ordering criteria in the Filter Clause'));

                if (!is_numeric($this->getOption('upperLimit')) || !is_numeric($this->getOption('lowerLimit')))
                    throw new \InvalidArgumentException(__('Limits must be numbers'));

                if ($this->getOption('upperLimit') < 0 || $this->getOption('lowerLimit') < 0)
                    throw new \InvalidArgumentException(__('Limits cannot be lower than 0'));

                // Check the bounds of the limits
                if ($this->getOption('upperLimit') < $this->getOption('lowerLimit'))
                    throw new \InvalidArgumentException(__('Upper limit must be higher than lower limit'));
            }

        } else {
            // Only supported two source types at the moment
            throw new \InvalidArgumentException(__('Unknown Source Type'));
        }

        if ($this->widget->widgetId != 0) {
            // Make sure we have a number in here
            if (!v::numeric()->validate($this->getOption('numItems')))
                throw new \InvalidArgumentException(__('The value in Number of Items must be numeric.'));

            if (!v::numeric()->notEmpty()->min(0)->validate($this->getOption('updateInterval')))
                throw new \InvalidArgumentException(__('Update Interval must be greater than or equal to 0'));
        }
    }

    /**
     * Add Media
     */
    public function add()
    {
        $this->setDuration(Sanitize::getInt('duration', $this->getDuration()));
        $this->setOption('xmds', true);
        $this->setOption('sourceId', Sanitize::getInt('sourceId'));
        $this->setOption('uri', Sanitize::getString('uri'));
        $this->setOption('dataSetId', Sanitize::getInt('dataSetId'));
        $this->setOption('updateInterval', 120);
        $this->setOption('speed', 2);

        // New tickers have template override set to 0 by add.
        // the edit form can then default to 1 when the element doesn't exist (for legacy)
        $this->setOption('overrideTemplate', 0);

        $this->setRawNode('template', null);
        $this->setRawNode('css', null);

        // Save the widget
        $this->validate();
        $this->saveWidget();
    }

    /**
     * Edit Media
     */
    public function edit()
    {
        // Source is selected during add() and cannot be edited.
        // Other properties
        $this->setDuration(Sanitize::getInt('duration', $this->getDuration()));
        $this->setOption('xmds', true);
        $this->setOption('uri', Sanitize::getString('uri'));
        $this->setOption('dataSetId', Sanitize::getInt('dataSetId'));
        $this->setOption('updateInterval', Sanitize::getInt('updateInterval', 120));
        $this->setOption('speed', Sanitize::getInt('speed', 2));
        $this->setOption('name', Sanitize::getString('name'));
        $this->setOption('effect', Sanitize::getString('effect'));
        $this->setOption('copyright', Sanitize::getString('copyright'));
        $this->setOption('numItems', Sanitize::getInt('numItems'));
        $this->setOption('takeItemsFrom', Sanitize::getString('takeItemsFrom'));
        $this->setOption('durationIsPerItem', Sanitize::getCheckbox('durationIsPerItem'));
        $this->setOption('itemsSideBySide', Sanitize::getCheckbox('itemsSideBySide'));
        $this->setOption('upperLimit', Sanitize::getInt('upperLimit'));
        $this->setOption('lowerLimit', Sanitize::getInt('lowerLimit'));
        $this->setOption('filter', Sanitize::getString('filter'));
        $this->setOption('ordering', Sanitize::getString('ordering'));
        $this->setOption('itemsPerPage', Sanitize::getInt('itemsPerPage'));
        $this->setOption('dateFormat', Sanitize::getString('dateFormat'));
        $this->setOption('allowedAttributes', Sanitize::getString('allowedAttributes'));
        $this->setOption('stripTags', Sanitize::getString('stripTags'));
        $this->setOption('backgroundColor', Sanitize::getString('backgroundColor'));
        $this->setOption('disableDateSort', Sanitize::getCheckbox('disableDateSort'));
        $this->setOption('textDirection', Sanitize::getString('textDirection'));
        $this->setOption('overrideTemplate', Sanitize::getCheckbox('overrideTemplate'));
        $this->setOption('templateId', Sanitize::getString('templateId'));

        // Text Template
        $this->setRawNode('template', Sanitize::getParam('ta_text', null));
        $this->setRawNode('css', Sanitize::getParam('ta_css', null));

        // Save the widget
        $this->validate();
        $this->saveWidget();
    }

    public function hoverPreview()
    {
        $name = $this->getOption('name');
        $url = urldecode($this->getOption('uri'));
        $sourceId = $this->getOption('sourceId', 1);

        // Default Hover window contains a thumbnail, media type and duration
        $output = '<div class="thumbnail"><img alt="' . $this->module->name . ' thumbnail" src="' . Theme::uri('img/forms/' . $this->getModuleType() . '.gif') . '"></div>';
        $output .= '<div class="info">';
        $output .= '    <ul>';
        $output .= '    <li>' . __('Type') . ': ' . $this->module->name . '</li>';
        $output .= '    <li>' . __('Name') . ': ' . $name . '</li>';

        if ($sourceId == 2)
            $output .= '    <li>' . __('Source') . ': DataSet</li>';
        else
            $output .= '    <li>' . __('Source') . ': <a href="' . $url . '" target="_blank" title="' . __('Source') . '">' . $url . '</a></li>';


        $output .= '    <li>' . __('Duration') . ': ' . $this->getDuration() . ' ' . __('seconds') . '</li>';
        $output .= '    </ul>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Get Resource
     * @param int $displayId
     * @return mixed
     */
    public function getResource($displayId = 0)
    {
        // Load in the template
        $data = [];
        $isPreview = (Sanitize::getCheckbox('preview') == 1);

        // Replace the View Port Width?
        $data['viewPortWidth'] = ($isPreview) ? $this->region->width : '[[ViewPortWidth]]';

        // What is the data source for this ticker?
        $sourceId = $this->getOption('sourceId', 1);

        // Information from the Module
        $itemsSideBySide = $this->getOption('itemsSideBySide', 0);
        $duration = $this->getDuration();
        $durationIsPerItem = $this->getOption('durationIsPerItem', 0);
        $numItems = $this->getOption('numItems', 0);
        $takeItemsFrom = $this->getOption('takeItemsFrom', 'start');
        $itemsPerPage = $this->getOption('itemsPerPage', 0);

        // Get the text out of RAW
        $text = $this->getRawNode('template', null);

        // Get the CSS Node
        $css = $this->getRawNode('css', '');

        // Handle older layouts that have a direction node but no effect node
        $oldDirection = $this->getOption('direction', 'none');

        if ($oldDirection == 'single')
            $oldDirection = 'fade';
        else if ($oldDirection != 'none')
            $oldDirection = 'marquee' . ucfirst($oldDirection);

        $effect = $this->getOption('effect', $oldDirection);

        $options = array(
            'type' => $this->getModuleType(),
            'fx' => $effect,
            'duration' => $duration,
            'durationIsPerItem' => (($durationIsPerItem == 0) ? false : true),
            'numItems' => $numItems,
            'takeItemsFrom' => $takeItemsFrom,
            'itemsPerPage' => $itemsPerPage,
            'speed' => $this->getOption('speed'),
            'originalWidth' => $this->region->width,
            'originalHeight' => $this->region->height,
            'previewWidth' => Sanitize::getDouble('width', 0),
            'previewHeight' => Sanitize::getDouble('height', 0),
            'scaleOverride' => Sanitize::getDouble('scale_override', 0)
        );

        // Generate a JSON string of substituted items.
        if ($sourceId == 2) {
            $items = $this->getDataSetItems($displayId, $isPreview, $text);
        } else {
            $items = $this->getRssItems($isPreview, $text);
        }

        // Return empty string if there are no items to show.
        if (count($items) == 0)
            return '';

        // Work out how many pages we will be showing.
        $pages = $numItems;

        if ($numItems > count($items) || $numItems == 0)
            $pages = count($items);

        $pages = ($itemsPerPage > 0) ? ceil($pages / $itemsPerPage) : $pages;
        $totalDuration = ($durationIsPerItem == 0) ? $duration : ($duration * $pages);

        // Replace and Control Meta options
        $data['controlMeta'] = '<!-- NUMITEMS=' . $pages . ' -->' . PHP_EOL . '<!-- DURATION=' . $totalDuration . ' -->';

        // Replace the head content
        $headContent = '';

        if ($itemsSideBySide == 1) {
            $headContent .= '<style type="text/css">';
            $headContent .= ' .item, .page { float: left; }';
            $headContent .= '</style>';
        }

        if ($this->getOption('textDirection') == 'rtl') {
            $headContent .= '<style type="text/css">';
            $headContent .= ' #content { direction: rtl; }';
            $headContent .= '</style>';
        }

        if ($this->getOption('backgroundColor') != '') {
            $headContent .= '<style type="text/css">';
            $headContent .= ' body { background-color: ' . $this->getOption('backgroundColor') . '; }';
            $headContent .= '</style>';
        }

        // Add the CSS if it isn't empty
        if ($css != '') {
            $headContent .= '<style type="text/css">' . $css . '</style>';
        }

        // Add our fonts.css file
        $headContent .= '<link href="' . $this->getResourceUrl('fonts.css') . ' rel="stylesheet" media="screen">';
        $headContent .= '<style type="text/css">' . file_get_contents(Theme::uri('css/client.css', true)) . '</style>';

        // Replace the Head Content with our generated javascript
        $data['head'] = $headContent;

        // Add some scripts to the JavaScript Content
        $javaScriptContent = '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery-1.11.1.min.js') . '"></script>';

        // Need the marquee plugin?
        if (stripos($effect, 'marquee') !== false)
            $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery.marquee.min.js') . '"></script>';

        // Need the cycle plugin?
        if ($effect != 'none')
            $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery-cycle-2.1.6.min.js') . '"></script>';

        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-layout-scaler.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-text-render.js') . '"></script>';

        $javaScriptContent .= '<script type="text/javascript">';
        $javaScriptContent .= '   var options = ' . json_encode($options) . ';';
        $javaScriptContent .= '   var items = ' . json_encode($items) . ';';
        $javaScriptContent .= '   $(document).ready(function() { ';
        $javaScriptContent .= '       $("body").xiboLayoutScaler(options); $("#content").xiboTextRender(options, items);';
        $javaScriptContent .= '   }); ';
        $javaScriptContent .= '</script>';

        // Replace the Head Content with our generated javascript
        $data['javaScript'] = $javaScriptContent;

        return $this->renderTemplate($data);
    }

    private function getRssItems($isPreview, $text)
    {
        // Make sure we have the cache location configured
        Library::ensureLibraryExists();

        // We might need to save the widget associated with this module
        //  for example if we have assigned an image to it
        $saveRequired = false;

        // Parse the text template
        $matches = '';
        preg_match_all('/\[.*?\]/', $text, $matches);

        Log::debug('Loading SimplePie to handle RSS parsing.' . urldecode($this->getOption('uri')) . '. Will substitute items with ' . $text);

        $feed = new \SimplePie();
        $feed->set_cache_location(Library::getLibraryCacheUri());
        $feed->set_feed_url(urldecode($this->getOption('uri')));
        $feed->force_feed(true);
        $feed->set_cache_duration(($this->getOption('updateInterval', 3600) * 60));
        $feed->handle_content_type();

        // Get a list of allowed attributes
        if ($this->getOption('allowedAttributes') != '') {
            $attrsStrip = array_diff($feed->strip_attributes, explode(',', $this->getOption('allowedAttributes')));
            //Debug::Audit(var_export($attrsStrip, true));
            $feed->strip_attributes($attrsStrip);
        }

        // Disable date sorting?
        if ($this->getOption('disableDateSort') == 1) {
            $feed->enable_order_by_date(false);
        }

        // Init
        $feed->init();

        $dateFormat = $this->getOption('dateFormat');

        if ($feed->error()) {
            Log::notice('Feed Error: ' . $feed->error());
            return array();
        }

        // Set an expiry time for the media
        $expires = time() + ($this->getOption('updateInterval', 3600) * 60);

        // Store our formatted items
        $items = array();

        foreach ($feed->get_items() as $item) {
            /* @var \SimplePie_Item $item */

            // Substitute for all matches in the template
            $rowString = $text;

            // Substitute
            foreach ($matches[0] as $sub) {
                $replace = '';

                // Pick the appropriate column out
                if (strstr($sub, '|') !== false) {
                    // Use the provided name space to extract a tag
                    $attributes = NULL;
                    if (substr_count($sub, '|') > 1)
                        list($tag, $namespace, $attributes) = explode('|', $sub);
                    else
                        list($tag, $namespace) = explode('|', $sub);

                    // What are we looking at
                    Log::debug('Namespace: ' . str_replace(']', '', $namespace) . '. Tag: ' . str_replace('[', '', $tag) . '. ');

                    // Are we an image place holder?
                    if (strstr($namespace, 'image') != false) {
                        // Try to get a link for the image
                        $link = null;

                        switch (str_replace('[', '', $tag)) {
                            case 'Link':
                                if ($enclosure = $item->get_enclosure()) {
                                    // Use the link to get the image
                                    $link = $enclosure->get_link();
                                }
                                break;

                            default:
                                // Default behaviour just tries to get the content from the tag provided (without a name space).
                                $tags = $item->get_item_tags('', str_replace('[', '', $tag));

                                if ($tags != null) {
                                    $link = (is_array($tags)) ? $tags[0]['data'] : '';
                                }
                        }

                        // If we have managed to resolve a link, download it and replace the tag with the downloaded
                        // image url
                        if ($link != NULL) {
                            // Grab the profile image
                            $file = MediaFactory::createModuleFile('ticker_' . md5($this->getOption('url') . $link), $link);
                            $file->isRemote = true;
                            $file->expires = $expires;
                            $file->save();

                            // Tag this layout with this file
                            $this->assignMedia($file->mediaId);

                            // We will need to save
                            $saveRequired = true;

                            $url = $this->getApp()->urlFor('library.download', ['id' => $file->mediaId, 'type' => 'image']);
                            $replace = ($isPreview) ? '<img src="' . $url . '?preview=1&width=' . $this->region->width . '&height=' . $this->region->height . '" ' . $attributes . '/>' : '<img src="' . $file->storedAs . '" ' . $attributes . ' />';
                        }
                    } else {
                        $tags = $item->get_item_tags(str_replace(']', '', $namespace), str_replace('[', '', $tag));

                        Log::notice('Tags:' . var_export($tags, true));

                        // If we find some tags then do the business with them
                        if ($tags != NULL) {
                            if ($attributes != NULL)
                                $replace = (is_array($tags)) ? $tags[0]['attribs'][''][str_replace(']', '', $attributes)] : '';
                            else
                                $replace = (is_array($tags)) ? $tags[0]['data'] : '';
                        }
                    }
                } else {

                    // Use the pool of standard tags
                    switch ($sub) {
                        case '[Name]':
                            $replace = $this->getOption('name');
                            break;

                        case '[Title]':
                            $replace = $item->get_title();
                            break;

                        case '[Description]':
                            $replace = $item->get_description();
                            break;

                        case '[Content]':
                            $replace = $item->get_content();
                            break;

                        case '[Copyright]':
                            $replace = $item->get_copyright();
                            break;

                        case '[Date]':
                            $replace = Date::getLocalDate($item->get_date('U'), $dateFormat);
                            break;

                        case '[PermaLink]':
                            $replace = $item->get_permalink();
                            break;

                        case '[Link]':
                            $replace = $item->get_link();
                            break;
                    }
                }

                if ($this->getOption('stripTags') != '') {
                    $config = \HTMLPurifier_Config::createDefault();
                    $config->set('HTML.ForbiddenElements', array_merge($feed->strip_htmltags, explode(',', $this->getOption('stripTags'))));
                    $purifier = new \HTMLPurifier($config);
                    $replace = $purifier->purify($replace);
                }

                // Substitute the replacement we have found (it might be '')
                $rowString = str_replace($sub, $replace, $rowString);
            }

            $items[] = $rowString;
        }

        // Copyright information?
        if ($this->getOption('copyright', '') != '') {
            $items[] = '<span id="copyright">' . $this->getOption('copyright') . '</span>';
        }

        // Should we save
        if ($saveRequired)
            $this->widget->save(['saveWidgetOptions' => false]);

        // Return the formatted items
        return $items;
    }

    private function getDataSetItems($displayId, $isPreview, $text)
    {
        // We might need to save the widget associated with this module
        //  for example if we have assigned an image to it
        $saveRequired = false;

        // Extra fields for data sets
        $dataSetId = $this->getOption('datasetid');
        $upperLimit = $this->getOption('upperLimit');
        $lowerLimit = $this->getOption('lowerLimit');
        $filter = $this->getOption('filter');
        $ordering = $this->getOption('ordering');

        Log::notice('Then template for each row is: ' . $text);

        // Set an expiry time for the media
        $expires = time() + ($this->getOption('updateInterval', 3600) * 60);

        // Combine the column id's with the dataset data
        $matches = '';
        preg_match_all('/\[(.*?)\]/', $text, $matches);

        $columnIds = array();

        foreach ($matches[1] as $match) {
            // Get the column id's we are interested in
            Log::notice('Matched column: ' . $match);

            $col = explode('|', $match);
            $columnIds[] = $col[1];
        }

        // Get the dataset results
        $dataSet = new \DataSet();
        if (!$dataSetResults = $dataSet->DataSetResults($dataSetId, implode(',', $columnIds), $filter, $ordering, $lowerLimit, $upperLimit, $displayId)) {
            return '';
        }

        // Create an array of header|datatypeid pairs
        $columnMap = array();
        foreach ($dataSetResults['Columns'] as $col) {
            $columnMap[$col['Text']] = $col;
        }

        Log::debug(var_export($columnMap, true));

        $items = array();

        foreach ($dataSetResults['Rows'] as $row) {
            // For each row, substitute into our template
            $rowString = $text;

            foreach ($matches[1] as $sub) {
                // Pick the appropriate column out
                $subs = explode('|', $sub);

                // The column header
                $header = $subs[0];
                $replace = $row[$header];

                // Check in the columns array to see if this is a special one
                if ($columnMap[$header]['DataTypeID'] == 4) {
                    // Download the image, alter the replace to wrap in an image tag
                    $file = MediaFactory::createModuleFile('ticker_dataset_' . md5($dataSetId . $columnMap[$header]['DataSetColumnID'] . $replace), str_replace(' ', '%20', htmlspecialchars_decode($replace)));
                    $file->isRemote = true;
                    $file->expires = $expires;
                    $file->save();

                    // Tag this layout with this file
                    $this->assignMedia($file->mediaId);

                    // We will need to save
                    $saveRequired = true;

                    $url = $this->getApp()->urlFor('library.download', ['id' => $file->mediaId, 'type' => 'image']);
                    $replace = ($isPreview) ? '<img src="' . $url . '?preview=1&width=' . $this->region->width . '&height=' . $this->region->height . '" />' : '<img src="' . $file->storedAs . '" />';
                }

                $rowString = str_replace('[' . $sub . ']', $replace, $rowString);
            }

            $items[] = $rowString;
        }

        if ($saveRequired)
            $this->widget->save(['saveWidgetOptions' => false]);

        return $items;
    }

    public function isValid()
    {
        // Can't be sure because the client does the rendering
        return 1;
    }
}
