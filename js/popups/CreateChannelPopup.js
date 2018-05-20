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
function CCreateChannelPopup()
{
	CAbstractPopup.call(this);
	
	this.channelName = ko.observable('');
	this.errorMessage = ko.observable('');
	this.channelName.subscribe(function () {
		this.errorMessage('');
	}, this);
	this.fOnCreateCallback = null;
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
		Ajax.send(
			'CreateChannel',
			{
				'Name': this.channelName().trim()
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
	var oResult = oResponse.Result;

	if (oResult)
	{
		this.addUserToChannel(oResult);
	}
	else
	{
		this.showError(TextUtils.i18n('%MODULENAME%/ERROR_CHANNEL_CREATING'));
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

CCreateChannelPopup.prototype.onAddUserToChannelResponse = function (oResponse)
{
	var oResult = oResponse.Result;

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
		this.showError(TextUtils.i18n('%MODULENAME%/ERROR_CHANNEL_CREATING'));
	}
};

module.exports = new CCreateChannelPopup();
