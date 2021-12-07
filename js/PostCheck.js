'use strict';

var
	_ = require('underscore'),
	Ajax = require('modules/%ModuleName%/js/Ajax.js'),
	Types = require('%PathToCoreWebclientModule%/js/utils/Types.js'),
	HeaderItemView = require('modules/%ModuleName%/js/views/HeaderItemView.js')
;

var PostCheck = {
	IsStoped: false,
	startCheck: function ()
	{
		this.getIsHaveUnseen();
		this.getLastPosts();
	},
	getLastPosts: function ()
	{
		Ajax.send('GetLastPosts', {}, this.onGetLastPostsResponse, this);
	},
	getIsHaveUnseen: function ()
	{
		Ajax.send('IsHaveUnseen', {}, this.onGetIsHaveUnseenResponse, this);
	},
	onGetLastPostsResponse: function (oResponse)
	{
		if (!(this.IsStoped || HeaderItemView.isCurrent()))
		{
			if (oResponse.Result && Types.isNonEmptyArray(oResponse.Result.Collection))
			{
				HeaderItemView.isUnseen(true);
			}
			setTimeout(_.bind(this.getLastPosts, this),1000);
		}
	},
	onGetIsHaveUnseenResponse: function (oResponse)
	{
		if (!(this.IsStoped || HeaderItemView.isCurrent()))
		{
			if (oResponse.Result)
			{
				HeaderItemView.isUnseen(true);
			}
		}
	},
	stopCheck: function ()
	{
		this.IsStoped = true;
	}
};
module.exports = PostCheck;
