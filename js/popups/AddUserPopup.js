'use strict';

var
	_ = require('underscore'),
	ko = require('knockout'),
	
	Ajax = require('modules/%ModuleName%/js/Ajax.js'),
	CAbstractPopup = require('%PathToCoreWebclientModule%/js/popups/CAbstractPopup.js'),
	TextUtils = require('%PathToCoreWebclientModule%/js/utils/Text.js'),
	Screens = require('%PathToCoreWebclientModule%/js/Screens.js')
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
	var oResult = oResponse.Result;

	if (oResult)
	{
		if(_.isFunction(this.fOnAddUserCallback))
		{
			this.fOnAddUserCallback();//update channels list
		}
	}
	else if (oResponse.ErrorCode)
	{
		this.showError(oResponse.ErrorCode);
	}
	this.closePopup();
};

module.exports = new CAddUserPopup();
