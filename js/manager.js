'use strict';

module.exports = function (oAppData) {
	var App = require('%PathToCoreWebclientModule%/js/App.js');
	
	if (App.getUserRole() === Enums.UserRole.NormalUser || App.getUserRole() === Enums.UserRole.Customer)
	{
		var
			TextUtils = require('%PathToCoreWebclientModule%/js/utils/Text.js'),
			Settings = require('modules/%ModuleName%/js/Settings.js'),
			PostCheck = require('modules/%ModuleName%/js/PostCheck.js'),		
			HeaderItemView = require('modules/%ModuleName%/js/views/HeaderItemView.js')
		;

		Settings.init(oAppData);

		return {
			enableModule: Settings.enableModule,

			/**
			 * Registers settings tab of chat module before application start.
			 * 
			 * @param {Object} ModulesManager
			 */
			start: function (ModulesManager) {
				ModulesManager.run(
					'SettingsWebclient',
					'registerSettingsTab',
					[
						function () { return require('modules/%ModuleName%/js/views/ChatSettingsFormView.js'); },
						Settings.HashModuleName,
						TextUtils.i18n('%MODULENAME%/LABEL_SETTINGS_TAB')
					]
				);
				PostCheck.startCheck();
			},

			/**
			 * Returns list of functions that are return module screens.
			 * 
			 * @returns {Object}
			 */
			getScreens: function () {
				var oScreens = {};
				oScreens[Settings.HashModuleName] = function () {
					return require('modules/%ModuleName%/js/views/MainView.js');
				};
				return oScreens;
			},

			/**
			 * Returns object of header item view of chat module.
			 * 
			 * @returns {Object}
			 */
			getHeaderItem: function () {
				return {
					item: HeaderItemView,
					name: Settings.HashModuleName
				};
			}
		};
	}
	
	return null;
};
