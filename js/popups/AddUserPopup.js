/* global App */

'use strict';

var
	_ = require('underscore'),
	ko = require('knockout'),
	
	Ajax = require('modules/%ModuleName%/js/Ajax.js'),
	CAbstractPopup = require('%PathToCoreWebclientModule%/js/popups/CAbstractPopup.js'),
	TextUtils = require('%PathToCoreWebclientModule%/js/utils/Text.js'),
	Screens = require('%PathToCoreWebclientModule%/js/Screens.js'),
	Utils = require('%PathToCoreWebclientModule%/js/utils/Common.js'),
	App = require('%PathToCoreWebclientModule%/js/App.js'),
	ModuleErrors = require('%PathToCoreWebclientModule%/js/ModuleErrors.js')
;

/**
 * @constructor
 */
function CAddUserPopup()
{
	CAbstractPopup.call(this);
	
	this.userPublicId = ko.observable('');

	this.fOnAddUserCallback = null;
	this.ChannelUUID = null;
	this.guestAutocompleteItem = ko.observable(null);
	this.guestAutocomplete = ko.observable('');
	this.guestAutocomplete.subscribe(function (sItem) {
		if (sItem === '')
		{
			this.guestAutocompleteItem(null);
		}
	}, this);
}

_.extendOwn(CAddUserPopup.prototype, CAbstractPopup.prototype);

CAddUserPopup.prototype.PopupTemplate = '%ModuleName%_AddUserPopup';

CAddUserPopup.prototype.onOpen = function (fOnAddUserCallback, ChannelUUID)
{
	this.fOnAddUserCallback = fOnAddUserCallback;
	this.ChannelUUID = ChannelUUID;
};

CAddUserPopup.prototype.addUser = function ()
{
	if (this.userPublicId().trim() !== '')
	{
		Ajax.send(
			'AddUserToChannel',
			{
				'UserPublicId': this.userPublicId().trim(),
				'ChannelUUID' : this.ChannelUUID
			},
			this.onChannelCreateResponse, 
			this
		);
	}
	else
	{
		this.showError(TextUtils.i18n('%MODULENAME%/ERROR_EMPTY_USER_PUBLIC_ID'));
	}
};

CAddUserPopup.prototype.showError = function (sMessage)
{
	Screens.showError(sMessage);
};

CAddUserPopup.prototype.onChannelCreateResponse = function (oResponse)
{
	var 
		oResult = oResponse.Result,
		sMessage = ''
	;

	if (oResult)
	{
		if(_.isFunction(this.fOnAddUserCallback))
		{
			this.fOnAddUserCallback();//update channels list
		}
	}
	else
	{
		sMessage = ModuleErrors.getErrorMessage(oResponse);
		if (sMessage)
		{
			this.showError(sMessage);
		}
		else
		{
			this.showError(TextUtils.i18n('%MODULENAME%/ERROR_DURING_ADDING_USER_TO_CHANNEL'));
		}
	}
	this.closePopup();
};

CAddUserPopup.prototype.autocompleteCallback = function (oTerm, fResponse)
{
	var	oParameters = {
			'Search': oTerm.term,
			'SortField': Enums.ContactSortField.Frequency,
			'SortOrder': 1,
			'Storage': 'team'
		}
	;

	Ajax.send('GetContacts',
		oParameters,
		function (oData) {
			var aList = [];
			if (oData && oData.Result && oData.Result && oData.Result.List)
			{
				aList = _.map(oData.Result.List, function (oItem) {
					return oItem && oItem.ViewEmail && oItem.ViewEmail !== App.getUserPublicId() ?
						(oItem.Name && 0 < Utils.trim(oItem.Name).length ?
							oItem.ForSharedToAll ? {value: oItem.Name, name: oItem.Name, email: oItem.ViewEmail, frequency: oItem.Frequency} :
							{value:'"' + oItem.Name + '" <' + oItem.ViewEmail + '>', name: oItem.Name, email: oItem.ViewEmail, frequency: oItem.Frequency} : {value: oItem.ViewEmail, name: '', email: oItem.ViewEmail, frequency: oItem.Frequency}) : null;
				}, this);
				aList = _.sortBy(_.compact(aList), function(num){
					return num.frequency;
				}).reverse();
			}
			fResponse(aList);
		},
		this,
		undefined,
		'Contacts'
	);
};

module.exports = new CAddUserPopup();
