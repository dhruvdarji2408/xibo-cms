<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2014 Daniel Garner
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
 *
 */ 
class clock extends Module
{
    public function __construct(database $db, user $user, $mediaid = '', $layoutid = '', $regionid = '', $lkid = '') {
        // The Module Type must be set - this should be a unique text string of no more than 50 characters.
        // It is used to uniquely identify the module globally.
        $this->type = 'clock';

        // This is the code schema version, it should be 1 for a new module and should be incremented each time the 
        // module data structure changes.
        // It is used to install / update your module and to put updated modules down to the display clients.
        $this->codeSchemaVersion = 1;
        
        // Must call the parent class
        parent::__construct($db, $user, $mediaid, $layoutid, $regionid, $lkid);
    }

    /**
     * Install or Update this module
     */
    public function InstallOrUpdate() {
        // This function should update the `module` table with information about your module.
        // The current version of the module in the database can be obtained in $this->schemaVersion
        // The current version of this code can be obtained in $this->codeSchemaVersion
        
        // $settings will be made available to all instances of your module in $this->settings. These are global settings to your module, 
        // not instance specific (i.e. not settings specific to the layout you are adding the module to).
        // $settings will be collected from the Administration -> Modules CMS page.
        // 
        // Layout specific settings should be managed with $this->SetOption in your add / edit forms.
        Debug::LogEntry('audit', 'Request to install or update with schemaversion: ' . $this->schemaVersion, 'clock', 'InstallOrUpdate');
        
        if ($this->schemaVersion <= 1) {
            // Install
            Debug::LogEntry('audit', 'Installing Clock module', 'clock', 'InstallOrUpdate');

            $this->InstallModule('Clock', 'Display a Clock', 'forms/library.gif', 1, 1, array());
        }
        else {
            // Update
            // No updates required to this module.
            // Call "$this->UpdateModule($name, $description, $imageUri, $previewEnabled, $assignable, $settings)" with the updated items
        }
    }

    /**
     * Form for updating the module settings
     */
    public function ModuleSettingsForm() {
        // Output any form fields (formatted via a Theme file)
        // These are appended to the bottom of the "Edit" form in Module Administration
        return '';
    }

    /**
     * Process any module settings
     */
    public function ModuleSettings() {
        // Process any module settings you asked for.
        
        // Return an array of the processed settings.
        return array();
    }

    /**
     * Return the Add Form as HTML
     * @return
     */
    public function AddForm() {
        // This is the logged in user and can be used to assess permissions
        $user =& $this->user;

        // All modules will have:
        //  $this->layoutid
        //  $this->regionid

        // You also have access to $settings, which is the array of settings you configured for your module.
        
        // The CMS provides the region width and height in case they are needed
        $rWidth = Kit::GetParam('rWidth', _REQUEST, _STRING);
        $rHeight = Kit::GetParam('rHeight', _REQUEST, _STRING);

        // All forms should set some meta data about the form.
        // Usually, you would want this meta data to remain the same.
        Theme::Set('form_id', 'ModuleForm');
        Theme::Set('form_action', 'index.php?p=module&mod=' . $this->type . '&q=Exec&method=AddMedia');
        Theme::Set('form_meta', '<input type="hidden" name="layoutid" value="' . $this->layoutid . '"><input type="hidden" id="iRegionId" name="regionid" value="' . $this->regionid . '"><input type="hidden" name="showRegionOptions" value="' . $this->showRegionOptions . '" />');
    
        // Any values for the form fields should be added to the theme here.

        // Modules should be rendered using the theme engine.
        $this->response->html = Theme::RenderReturn('media_form_clock_add');

        $this->response->dialogTitle = __('Add Clock');
        
        // The response object outputs the required JSON object to the browser
        // which is then processed by the CMS JavaScript library (xibo-cms.js).
        $this->response->AddButton(__('Cancel'), 'XiboSwapDialog("index.php?p=timeline&layoutid=' . $this->layoutid . '&regionid=' . $this->regionid . '&q=RegionOptions")');
        $this->response->AddButton(__('Save'), '$("#ModuleForm").submit()');

        // The response must be returned.
        return $this->response;
    }

    /**
     * Add Media to the Database
     * @return
     */
    public function AddMedia() {
        // Same member variables as the Form call, except with POST variables for your form fields.
        $layoutid   = $this->layoutid;
        $regionid   = $this->regionid;
        $mediaid    = $this->mediaid;

        // You are required to set a media id, which should be unique.
        $this->mediaid  = md5(uniqid());

        // You must also provide a duration (all media items must provide this field)
        $this->duration = Kit::GetParam('duration', _POST, _INT, 0);

        // Should have built the media object entirely by this time
        // This saves the Media Object to the Region
        $this->UpdateRegion();

        // Usually you will want to load the region options form again once you have added your module.
        // In some cases you will want to load the edit form for that module
        $this->response->loadForm = true;
        $this->response->loadFormUri = "index.php?p=timeline&layoutid=$this->layoutid&regionid=$this->regionid&q=RegionOptions";
        
        return $this->response;
    }

    /**
     * Return the Edit Form as HTML
     * @return
     */
    public function EditForm() {
        
        // Edit calls are the same as add calls, except you will to check the user has permissions to do the edit
        if (!$this->auth->edit)
        {
            $this->response->SetError('You do not have permission to edit this assignment.');
            $this->response->keepOpen = false;
            return $this->response;
        }

        // All forms should set some meta data about the form.
        // Usually, you would want this meta data to remain the same.
        Theme::Set('form_id', 'ModuleForm');
        Theme::Set('form_action', 'index.php?p=module&mod=' . $this->type . '&q=Exec&method=AddMedia');
        Theme::Set('form_meta', '<input type="hidden" name="layoutid" value="' . $this->layoutid . '"><input type="hidden" id="iRegionId" name="regionid" value="' . $this->regionid . '"><input type="hidden" name="showRegionOptions" value="' . $this->showRegionOptions . '" />');
    
        // Any values for the form fields should be added to the theme here.
        Theme::Set('duration', $this->duration);

        // Modules should be rendered using the theme engine.
        $this->response->html = Theme::RenderReturn('media_form_clock_edit');

        $this->response->dialogTitle = __('Edit Clock');
        
        // The response object outputs the required JSON object to the browser
        // which is then processed by the CMS JavaScript library (xibo-cms.js).
        $this->response->AddButton(__('Cancel'), 'XiboSwapDialog("index.php?p=timeline&layoutid=' . $this->layoutid . '&regionid=' . $this->regionid . '&q=RegionOptions")');
        $this->response->AddButton(__('Save'), '$("#ModuleForm").submit()');

        // The response must be returned.
        return $this->response;
    }

    /**
     * Edit Media in the Database
     * @return
     */
    public function EditMedia() {
        // Edit calls are the same as add calls, except you will to check the user has permissions to do the edit
        if (!$this->auth->edit)
        {
            $this->response->SetError('You do not have permission to edit this assignment.');
            $this->response->keepOpen = false;
            return $this->response;
        }

        // You must also provide a duration (all media items must provide this field)
        $this->duration = Kit::GetParam('duration', _POST, _INT, 0);

        // Should have built the media object entirely by this time
        // This saves the Media Object to the Region
        $this->UpdateRegion();

        // Usually you will want to load the region options form again once you have added your module.
        // In some cases you will want to load the edit form for that module
        $this->response->loadForm = true;
        $this->response->loadFormUri = "index.php?p=timeline&layoutid=$this->layoutid&regionid=$this->regionid&q=RegionOptions";
        
        return $this->response;
    }

    /**
     * Preview
     * @param <double> $width
     * @param <double> $height
     * @return <string>
     */
    public function Preview($width, $height) {
        // Each module should be able to output a preview to use in the Layout Designer
        
        // If preview is not enabled for your module you can hand off to the base class
        // and it will output a basic preview for you
        if ($this->previewEnabled == 0)
            return parent::Preview ($width, $height);
        
        // In most cases your preview will want to load the GetResource call associated with the module
        // This imitates the client
        return $this->PreviewAsClient($width, $height);
    }

    /**
     * GetResource
     *     Return the rendered resource to be used by the client (or a preview)
     *     for displaying this content.
     * @param integer $displayId If this comes from a real client, this will be the display id.
     */
    public function GetResource($displayId = 0) {
        // Behave exactly like the client.

        // A template is provided which contains a number of different libraries that might
        // be useful (jQuery, etc).
        // You can provide your own template, or just output the HTML directly in this method. It is up to you.
        $template = file_get_contents('modules/preview/HtmlTemplateForClock.html');

        // If we are coming from a CMS preview or the Layout Designer we will have some additional variables passed in
        // These will not be passed in from the client.
        $width = Kit::GetParam('width', _REQUEST, _DOUBLE);
        $height = Kit::GetParam('height', _REQUEST, _DOUBLE);

        // Render our clock

        // After body content
        $javaScriptContent  = '<script>' . file_get_contents('modules/preview/vendor/jquery-1.11.1.min.js') . '</script>';
        $javaScriptContent .= '<script>' . file_get_contents('modules/preview/vendor/moment.js') . '</script>';

        // Replace the After body Content
        $template = str_replace('<!--[[[JAVASCRIPTCONTENT]]]-->', $javaScriptContent, $template);

        // Do whatever it is you need to do to render your content.
        // Return that content.
        return $template;
    }

    public function HoverPreview() {
        // Default Hover window contains a thumbnail, media type and duration
        $output = parent::HoverPreview();

        // You can add anything you like to this, or completely replace it

        return $output;
    }
    
    public function IsValid() {
        // Using the information you have in your module calculate whether it is valid or not.
        // 0 = Invalid
        // 1 = Valid
        // 2 = Unknown
        return 1;
    }
}
?>
