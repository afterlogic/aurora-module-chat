'use strict';

var
	_ = require('underscore'),
	ko = require('knockout'),
	
	Ajax = require('modules/%ModuleName%/js/Ajax.js'),
	CAbstractPopup = require('%PathToCoreWebclientModule%/js/popups/CAbstractPopup.js'),
	TextUtils = require('%PathToCoreWebclientModule%/js/utils/Text.js')
;

/**
 * @constructor
 */
function CRenameChannelPopup()
{
	CAbstractPopup.call(this);
	
	this.channelName = ko.observable('');
	this.channelUUID = ko.observable('');
	this.errorMessage = ko.observable('');
	this.channelName.subscribe(function () {
		this.errorMessage('');
	}, this);
	this.fOnRenameCallback = null;
}

_.extendOwn(CRenameChannelPopup.prototype, CAbstractPopup.prototype);

CRenameChannelPopup.prototype.PopupTemplate = '%ModuleName%_RenameChannelPopup';

CRenameChannelPopup.prototype.onOpen = function (ChannelUUID, ChannelName,fOnRenameCallback)
{
	this.fOnRenameCallback = fOnRenameCallback;
	this.channelName(ChannelName);
	this.channelUUID(ChannelUUID);
	this.errorMessage('');
};

CRenameChannelPopup.prototype.renameChannel = function ()
{
	if (this.channelName().trim() !== '')
	{
		Ajax.send(
			'RenameChannel',
			{
				'ChannelUUID': this.channelUUID(),
				'Name': this.channelName().trim()
			},
			this.onChannelRenameResponse, 
			this
		);
	}
	else
	{
		this.showError(TextUtils.i18n('%MODULENAME%/ERROR_EMPTY_CHANNEL_NAME'));
	}
};

CRenameChannelPopup.prototype.showError = function (sMessage)
{
	this.errorMessage(sMessage);
};

CRenameChannelPopup.prototype.onChannelRenameResponse = function (oResponse)
{
	var oResult = oResponse.Result;

	if (oResult)
	{
		if(_.isFunction(this.fOnRenameCallback))
		{
			this.fOnRenameCallback();
		}
		this.channelName('');
		this.channelUUID('');
		this.closePopup();
		this.errorMessage('');
	}
	else
	{
		this.showError(TextUtils.i18n('%MODULENAME%/ERROR_CHANNEL_RENAMING'));
	}
};

module.exports = new CRenameChannelPopup();
