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
}

_.extendOwn(CCreateChannelPopup.prototype, CAbstractPopup.prototype);

CCreateChannelPopup.prototype.PopupTemplate = '%ModuleName%_CreateChannelPopup';

CCreateChannelPopup.prototype.createChannel = function ()
{
	if (this.channelName().trim() !== '')
	{
		Ajax.send(
			'CreateChannel',
			{
				'Name': this.channelName()
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
		
	}
	this.closePopup();
	this.errorMessage('');
	//update channels list
};

module.exports = new CCreateChannelPopup();
