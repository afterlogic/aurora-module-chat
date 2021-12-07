'use strict';

var
	_ = require('underscore'),
	ko = require('knockout'),
	
	Ajax = require('modules/%ModuleName%/js/Ajax.js'),
	CAbstractPopup = require('%PathToCoreWebclientModule%/js/popups/CAbstractPopup.js'),
	TextUtils = require('%PathToCoreWebclientModule%/js/utils/Text.js'),
	Utils = require('%PathToCoreWebclientModule%/js/utils/Common.js'),
	App = require('%PathToCoreWebclientModule%/js/App.js'),
	ModuleErrors = require('%PathToCoreWebclientModule%/js/ModuleErrors.js')
;

/**
 * @constructor
 */
function CCreateChannelPopup()
{
	CAbstractPopup.call(this);
	
	this.channelName = ko.observable('');
	this.errorMessage = ko.observable('');
	this.channelName.subscribe(function () {
		this.errorMessage('');
	}, this);
	this.fOnCreateCallback = null;
	this.guestAutocompleteItem = ko.observable(null);
	this.guestAutocomplete = ko.observable('');
	this.isSaving = ko.observable(false);
	this.guestAutocomplete.subscribe(function (sItem) {
		if (sItem === '')
		{
			this.guestAutocompleteItem(null);
		}
	}, this);
}

_.extendOwn(CCreateChannelPopup.prototype, CAbstractPopup.prototype);

CCreateChannelPopup.prototype.PopupTemplate = '%ModuleName%_CreateChannelPopup';

CCreateChannelPopup.prototype.onOpen = function (fOnCreateCallback)
{
	this.fOnCreateCallback = fOnCreateCallback;
	this.channelName('');
	this.errorMessage('');
};

CCreateChannelPopup.prototype.createChannel = function ()
{
	if (this.channelName().trim() !== '')
	{
		this.isSaving(true);
		Ajax.send(
			'CreateChannel',
			{
				'Name': ''/*this.channelName().trim()*/
			},
			this.onChannelCreateResponse, 
			this
		);
	}
	else
	{
		this.showError(TextUtils.i18n('%MODULENAME%/ERROR_EMPTY_CHANNEL_NAME'));
	}
};

CCreateChannelPopup.prototype.showError = function (sMessage)
{
	this.errorMessage(sMessage);
};

CCreateChannelPopup.prototype.onChannelCreateResponse = function (oResponse)
{
	var
		oResult = oResponse.Result,
		sMessage = ''
	;

	if (oResult)
	{
		this.addUserToChannel(oResult);
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
			this.showError(TextUtils.i18n('%MODULENAME%/ERROR_CHANNEL_CREATING'));
		}
		this.isSaving(false);
	}
};

CCreateChannelPopup.prototype.addUserToChannel = function (ChannelUUID)
{
	Ajax.send(
		'AddUserToChannel',
		{
			'UserPublicId': this.channelName().trim(),
			'ChannelUUID' : ChannelUUID
		},
		this.onAddUserToChannelResponse, 
		this
	);
};

CCreateChannelPopup.prototype.onAddUserToChannelResponse = function (oResponse, oRequest)
{
	var
		oResult = oResponse.Result,
		sMessage = ''
	;

	if (oResult)
	{
		if(_.isFunction(this.fOnCreateCallback))
		{
			this.fOnCreateCallback();
		}
		this.channelName('');
		this.closePopup();
		this.errorMessage('');
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
			this.showError(TextUtils.i18n('%MODULENAME%/ERROR_CHANNEL_CREATING'));
		}
		//remove self from empty channel
		Ajax.send(
			'DeleteUserFromChannel',
			{
				'UserPublicId': App.getUserPublicId(),
				'ChannelUUID' : oRequest.Parameters.ChannelUUID
			},
			function(){}, 
			this
		);
	}
	this.isSaving(false);
};

CCreateChannelPopup.prototype.autocompleteCallback = function (oTerm, fResponse)
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
		'Contacts'
	);
};

module.exports = new CCreateChannelPopup();
