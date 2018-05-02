'use strict';

var
	_ = require('underscore'),
	$ = require('jquery'),
	ko = require('knockout'),
	moment = require('moment'),
	
	TextUtils = require('%PathToCoreWebclientModule%/js/utils/Text.js'),
	Types = require('%PathToCoreWebclientModule%/js/utils/Types.js'),
	Utils = require('%PathToCoreWebclientModule%/js/utils/Common.js'),
	
	App = require('%PathToCoreWebclientModule%/js/App.js'),
	
	CAbstractScreenView = require('%PathToCoreWebclientModule%/js/views/CAbstractScreenView.js'),
	Popups = require('%PathToCoreWebclientModule%/js/Popups.js'),
	
	Ajax = require('modules/%ModuleName%/js/Ajax.js'),
	HeaderItemView = require('modules/%ModuleName%/js/views/HeaderItemView.js'),
	PostCheck = require('modules/%ModuleName%/js/PostCheck.js'),
	CreateChannelPopup = require('modules/%ModuleName%/js/popups/CreateChannelPopup.js')
;

/**
 * View that is used as screen of chat module. Inherits from CAbstractScreenView that has showing and hiding methods.
 * 
 * @constructor
 */
function CChatView()
{
	CAbstractScreenView.call(this, '%ModuleName%');
	
	/**
	 * Text for displaying in browser title when chat screen is shown.
	 */
	this.browserTitle = ko.observable(TextUtils.i18n('%MODULENAME%/HEADING_BROWSER_TAB'));
	
	this.bAllowReply = (App.getUserRole() === Enums.UserRole.NormalUser);
	
	this.posts = ko.observableArray([]);
	this.gettingMore = ko.observable(false);
	this.offset = ko.observable(0);
	this.postsPerPage = 10;
	// Quick Reply Part
	
	this.domQuickReply = ko.observable(null);
	this.replyText = ko.observable('');
	this.replyTextFocus = ko.observable(false);
	this.replySendingStarted = ko.observable(false);
	this.replySavingStarted = ko.observable(false);
	this.replyAutoSavingStarted = ko.observable(false);
	
	this.isQuickReplyActive = ko.computed(function () {
		return this.replyText().length > 0 || this.replyTextFocus();
	}, this);
	this.replyLoadingText = ko.computed(function () {
		if (this.replySendingStarted())
		{
			return TextUtils.i18n('COREWEBCLIENT/INFO_SENDING');
		}
		else if (this.replySavingStarted())
		{
			return TextUtils.i18n('MAIL/INFO_SAVING');
		}
		return '';
	}, this);
	
	this.saveButtonText = ko.computed(function () {
		return this.replyAutoSavingStarted() ? TextUtils.i18n('MAIL/ACTION_SAVE_IN_PROGRESS') : TextUtils.i18n('MAIL/ACTION_SAVE');
	}, this);
	
	this.scrolledPostsDom = ko.observable(null);
	this.scrollTrigger = ko.observable(0);
	this.bBottom = false;
	this.replyTextFocus.subscribe(function () {
		this.scrollIfNecessary(500);
	}, this);
	this.postsOnPage = ko.observable(this.postsPerPage);
	App.broadcastEvent('%ModuleName%::ConstructView::after', {'Name': this.ViewConstructorName, 'View': this});
	this.IsCheckStarted = ko.observable(false);
	this.bigButtonCommand = Utils.createCommand(this, function () {
		Popups.showPopup(CreateChannelPopup, []);
	});
	this.channelList = ko.observableArray([]);
	this.selectedChannel = ko.observable(null);
	this.currentChannelPosts = ko.observableArray([]);
//	this.selectedChannel.subscribe(function () {
//		this.setCurrentChannelPosts();
//	}, this);
}

_.extendOwn(CChatView.prototype, CAbstractScreenView.prototype);

CChatView.prototype.ViewTemplate = '%ModuleName%_MainView';
CChatView.prototype.ViewConstructorName = 'CChatView';

/**
 * Scrolls post list to bottom after posts getting if it was scrolled to bottom earlier.
 * 
 * @param {int} iDelay delay for scrolling in milliseconds.
 */
CChatView.prototype.scrollIfNecessary = function (iDelay)
{
	if (this.scrolledPostsDom() && this.scrolledPostsDom()[0])
	{
		var oScrolledPostsDom = this.scrolledPostsDom()[0];
		this.bBottom = (oScrolledPostsDom.clientHeight + oScrolledPostsDom.scrollTop) <= oScrolledPostsDom.scrollHeight;
	}
	
	if (this.bBottom)
	{
		_.delay(_.bind(function () {
			this.scrollTrigger(this.scrollTrigger() + 1);
		}, this), iDelay);
	}
};

/**
 * Called every time when screen is shown. Requests posts count from server at first.
 */
CChatView.prototype.onShow = function ()
{
	//After showing chat tab we stopping service which checking for new posts
	PostCheck.stopCheck();
	HeaderItemView.isUnseen(false);
	if (this.posts().length === 0)
	{
		Ajax.send('GetPostsCount', null, function (oResponse) {
			var iCount = Types.pInt(oResponse && oResponse.Result);
			if (iCount > 10)
			{
				this.offset(iCount - 10);
			}
			this.getPosts(/*bStartPostsCheck*/true);
		}, this);
	}
	this.getChannels();
};

/**
 * Changes posts offset and request them with new value to get earlier posts.
 */
CChatView.prototype.showMore = function ()
{
	if (this.offset() > 0)
	{
		this.gettingMore(true);
	}
	this.offset((this.offset() >= this.postsPerPage) ? this.offset() - this.postsPerPage : 0);
	this.postsOnPage(this.postsOnPage() + this.postsPerPage);
	this.getPosts();
};

/**
 * Requests posts from the server with given offset and very big limit.
 * @param {boolean} bStartPostsCheck Indicates that process which checking for new posts would be started after getting posts 
 */
CChatView.prototype.getPosts = function (bStartPostsCheck)
{
	//Start checking of new posts after getting response for "getPosts" request
	Ajax.send('GetPosts', {Offset: this.offset(), Limit: 10},
		_.bind(function (oResponse, oRequest) {
			this.onGetPostsResponse(oResponse, oRequest);
			if (bStartPostsCheck === true && !this.IsCheckStarted())
			{
				this.getLastPosts();
				this.IsCheckStarted(true);
			}
		}, this),
		this);
};

CChatView.prototype.getLastPosts = function ()
{
	Ajax.send('GetLastPosts', {'IsUpdateLastShowPostsDate':HeaderItemView.isCurrent()}, this.onGetLastPostsResponse, this, /*iTimeout*/30000);
};

/**
 * Prepares display values of text and date fields. Broadcasts event before post displaying.
 * Adds prepared post into posts array.
 * 
 * @param {Object} oPost Post object.
 * @param {boolean} bEnd Indicates if post should be added to the end of the posts array or to the its beginning.
 * @param {boolean} bOwn Indicates own post.
 */
CChatView.prototype.addPost = function (oPost, bEnd, bOwn)
{
	oPost.displayDate = this.getDisplayDate(moment.utc(oPost.date));
	oPost.displayText = oPost.is_html ? oPost.text : TextUtils.encodeHtml(oPost.text);
	oPost.isOwn = bOwn;

	App.broadcastEvent('Chat::DisplayPost::before', {'Post': oPost, 'Own': bOwn});

	if (bEnd)
	{
		this.getPostsByChannelObservable(oPost.channelUUID).push(oPost);
	}
	else
	{
		this.getPostsByChannelObservable(oPost.channelUUID).unshift(oPost);
	}
};

/**
 * Posts request callback. Parses server response with posts. Adds new posts to the end or begining of the posts array.
 * 
 * @param {Object} oResponse Object with data from server.
 * @param {Object} oRequest Object with parameters wich were used for request to the server.
 */
CChatView.prototype.onGetPostsResponse = function (oResponse, oRequest)
{
	if (oResponse.Result && Types.isNonEmptyArray(oResponse.Result.Collection))
	{
		var
			aPosts = oResponse.Result.Collection
		;
//		if (this.posts().length === 0)
//		{
//			_.each(aPosts, _.bind(function (oPost) {
//				this.addPost(oPost, true, oPost.userId === App.getUserId());
//			}, this));
//			this.scrollIfNecessary(500);
//		}
//		else
//		{
			_.each(this.channelList(), _.bind(function (oChannel) {
				this.removeOwnPosts(oChannel.UUID);
			},this));
			
			var
				iFirstIndex = 0,
				iLastIndex = aPosts.length - 1
			;
			
			if (typeof oResponse.Result.Offset !== 'undefined')
			{
				/**
				* Adds all new posts to the beginning of the post list.
				*/
				for (var iIndex = iLastIndex; iIndex >= 0; iIndex--)
				{
					this.addPost(aPosts[iIndex], false, aPosts[iIndex].userId === App.getUserId());
				}
			}
			else
			{
				/**
				 * Adds all new posts to the end of the post list.
				 */
				for (var iIndex = iFirstIndex; iIndex < aPosts.length; iIndex++)
				{
					this.addPost(aPosts[iIndex], true, aPosts[iIndex].userId === App.getUserId());
				}
				this.scrollIfNecessary(500);
			}
//		}
		_.each(this.channelList(), _.bind(function (oChannel) {
			this.removeExtraPosts(oChannel.UUID);
		},this));
		this.setCurrentChannelPosts();
		if (!HeaderItemView.isCurrent())
		{
			HeaderItemView.isUnseen(true);
		}
	}
	this.gettingMore(false);
};

/**
 * Removes recent own posts that were added between posts requests.
 */
CChatView.prototype.removeOwnPosts = function (ChannelUUID)
{
	for (var i = 0; i < this.getPostsByChannel(ChannelUUID).length; i++)
	{
		if (this.getPostsByChannel(ChannelUUID)[i].recent)
		{
			this.getPostsByChannelObservable(ChannelUUID).remove(this.getPostsByChannel(ChannelUUID)[i]);
		}
	}
};

/**
 * Formats date for displaying.
 * 
 * @param {Object} oMomentUtc Moment date object in utc.
 */
CChatView.prototype.getDisplayDate = function (oMomentUtc)
{
	var
		oLocal = oMomentUtc.local(),
		oNow = moment()
	;
	
	if (oNow.diff(oLocal, 'days') === 0)
	{
		return oLocal.format('HH:mm:ss');
	}
	else
	{
		return oLocal.format('MMM Do HH:mm:ss');
	}
};

/**
 * Sends request to the server for creating post.
 * 
 * @returns {Boolean} Prevents bubbling of keyup event.
 */
CChatView.prototype.sendPost = function ()
{
	if (this.bAllowReply && $.trim(this.replyText()) !== '')
	{
		var sDate = moment().utc().format('YYYY-MM-DD HH:mm:ss');
		Ajax.send('CreatePost', {'Text': this.replyText(), 'ChannelUUID': this.selectedChannelUUID()}, false, this);
		this.addPost({
				userId: App.getUserId(),
				name: App.getUserPublicId(),
				text: this.replyText(),
				date: sDate,
				recent: true,
				channelUUID: this.selectedChannelUUID()
			},
			true,
			true
		);
		this.replyText('');
	}
	return false;
};

CChatView.prototype.onGetLastPostsResponse = function (oResponse, oRequest)
{
	this.onGetPostsResponse(oResponse, oRequest);
	setTimeout(_.bind(this.getLastPosts, this),1000);
};

CChatView.prototype.removeExtraPosts = function (ChannelUUID)
{
	var iNumberOfExtraPosts = this.getPostsByChannel(ChannelUUID).length - this.postsOnPage();
	if (iNumberOfExtraPosts > 0)
	{
		for (var i = 0; i < iNumberOfExtraPosts; i++)
		{
			this.getPostsByChannelObservable(ChannelUUID).remove(this.getPostsByChannel(ChannelUUID)[i]);
		}
	}
};

CChatView.prototype.setTextFocus = function ()
{
	$('#reply_text').focus();
};

CChatView.prototype.getChannels = function ()
{
//	Ajax.send('GetUserChannels', null, this.onGetChannelsResponse, this);
	Ajax.send('GetChannels', null, this.onGetChannelsResponse, this);
};

CChatView.prototype.onGetChannelsResponse = function (oResponse)
{
	if (oResponse.Result)
	{
		this.channelList(oResponse.Result);
		if (this.channelList().length > 0)
		{
			this.selectedChannel(this.channelList()[0]);
			_.each(this.channelList(), _.bind(function (oChannel) {
				this.posts()[oChannel.UUID] = ko.observableArray([]);
			},this));
		}
	}
};

CChatView.prototype.selectedChannelUUID = function ()
{
	return this.selectedChannel() ? this.selectedChannel().UUID : '';
};

CChatView.prototype.showChannel = function (UUID)
{
	var oChannel = _.find(this.channelList(), function(oChannel){
		return oChannel.UUID === UUID; 
	});
	this.selectedChannel(oChannel);
	this.setCurrentChannelPosts();
};

CChatView.prototype.getPostsByChannelObservable = function (UUID)
{
	return (this.posts()[UUID]) ? this.posts()[UUID] : null;
};

CChatView.prototype.getPostsByChannel = function (UUID)
{
	return (this.posts()[UUID]()) ? this.posts()[UUID]() : [];
};

CChatView.prototype.setCurrentChannelPosts = function ()
{
	this.currentChannelPosts(this.getPostsByChannel(this.selectedChannelUUID()));
};

module.exports = new CChatView();
