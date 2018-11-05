'use strict';

var
	_ = require('underscore'),
	$ = require('jquery'),
	ko = require('knockout'),
	moment = require('moment'),
	
	Screens = require('%PathToCoreWebclientModule%/js/Screens.js'),
	TextUtils = require('%PathToCoreWebclientModule%/js/utils/Text.js'),
	Types = require('%PathToCoreWebclientModule%/js/utils/Types.js'),
	Utils = require('%PathToCoreWebclientModule%/js/utils/Common.js'),
	
	App = require('%PathToCoreWebclientModule%/js/App.js'),
	
	CAbstractScreenView = require('%PathToCoreWebclientModule%/js/views/CAbstractScreenView.js'),
	Popups = require('%PathToCoreWebclientModule%/js/Popups.js'),
	ConfirmPopup = require('%PathToCoreWebclientModule%/js/popups/ConfirmPopup.js'),
	
	Ajax = require('modules/%ModuleName%/js/Ajax.js'),
	HeaderItemView = require('modules/%ModuleName%/js/views/HeaderItemView.js'),
	PostCheck = require('modules/%ModuleName%/js/PostCheck.js'),
	CreateChannelPopup = require('modules/%ModuleName%/js/popups/CreateChannelPopup.js'),
	AddUserPopup = require('modules/%ModuleName%/js/popups/AddUserPopup.js'),
	RenameChannelPopup = require('modules/%ModuleName%/js/popups/RenameChannelPopup.js'),

	Enums = window.Enums
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
	this.channels = ko.observableArray([]);
	this.gettingMore = ko.observable(false);
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
	App.broadcastEvent('%ModuleName%::ConstructView::after', {'Name': this.ViewConstructorName, 'View': this});
	this.bigButtonCommand = Utils.createCommand(this, this.createChannel);
	this.addUserCommand = Utils.createCommand(this, this.addUser);
	this.selectedChannel = ko.observable(null);
	this.currentChannelPosts = ko.computed(function () {
		if (this.selectedChannel())
		{
			return this.selectedChannel().PostsCollection();
		}
		return [];
	}, this);
	this.currentChannelUsers = ko.computed(function () {
		if (this.selectedChannel())
		{
			return this.selectedChannel().UsersCollection();
		}
		return [];
	}, this);
	this.deleteTitle = TextUtils.i18n('COREWEBCLIENT/ACTION_DELETE_ADDRESS');
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

	if (this.channels().length <= 0)
	{
		Ajax.send('GetUserChannelsWithPosts',
			{
				'Limit': this.postsPerPage
			},
			this.onGetUserChannelsWithPostsResponse,
			this
		);
	}
};

CChatView.prototype.onGetUserChannelsWithPostsResponse = function (oResponse, oRequest)
{
	if (oResponse.Result && oResponse.Result.Collection && !_.isEmpty(oResponse.Result.Collection))
	{
		this.initChannelsData(oResponse.Result.Collection);
	}
	this.getLastPosts();
};

/**
 * Changes posts offset and request them with new value to get earlier posts.
 */
CChatView.prototype.showMore = function ()
{
	if (this.selectedChannel().Offset() > 0)
	{
		this.gettingMore(true);
	}
	this.selectedChannel().Offset((this.selectedChannel().Offset() >= this.postsPerPage) ? this.selectedChannel().Offset() - this.postsPerPage : 0);
	this.selectedChannel().PostsOnPage(this.selectedChannel().PostsOnPage() + this.postsPerPage);
	this.getPreviousPosts();
};

CChatView.prototype.getLastPosts = function ()
{
	Ajax.send('GetLastPosts', {'IsUpdateLastShowPostsTimestamp':HeaderItemView.isCurrent()}, this.onGetLastPostsResponse, this, /*iTimeout*/30000);
};

CChatView.prototype.getPreviousPosts = function ()
{
	var iLimit = this.postsPerPage;

	if (this.selectedChannel().Offset() === 0 &&
		(this.selectedChannel().PostsCount() % this.postsPerPage) !== 0)
	{
		iLimit = this.selectedChannel().PostsCount() % this.postsPerPage;
	}
	Ajax.send('GetPosts', {
			Offset: this.selectedChannel().Offset(),
			Limit: iLimit,
			ChannelUUID: this.selectedChannel().UUID
		},
		this.onGetPreviousPostsResponse,
		this);
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
	var
		oCahnnel = this.getChannelByUUID(oPost.channelUUID),
		oNearestPost = null
	;

	oPost.displayDate = ko.observable(this.getDisplayDate(moment.utc(oPost.date)));
	oPost.displayText = ko.observable(oPost.isHtml ? oPost.text : TextUtils.encodeHtml(oPost.text));
	oPost.isOwn = bOwn;
	oPost.hideHeader = ko.observable(false);
	oPost.hideMessageDate = ko.observable(true);

	if (oPost.displayText() !== '')
	{
		App.broadcastEvent('Chat::DisplayPost::before', {'Post': oPost, 'Own': bOwn});

		if (oCahnnel && oCahnnel.PostsCollection)
		{
			if (bEnd)
			{
				//hide header if nearest posts have the same author and time
				oNearestPost = oCahnnel.PostsCollection()[oCahnnel.PostsCollection().length - 1];
				if (oNearestPost &&
						oNearestPost.name === oPost.name &&
						oNearestPost.displayDate() === oPost.displayDate()
				)
				{
					oPost.hideHeader(true);
				}
				oCahnnel.PostsCollection.push(oPost);
			}
			else
			{
				//hide header if nearest posts have the same author and time
				oNearestPost = oCahnnel.PostsCollection()[0];

				if (oNearestPost &&
						oNearestPost.name === oPost.name &&
						oNearestPost.displayDate() === oPost.displayDate()
				)
				{
					oNearestPost.hideHeader(true);
				}
				oCahnnel.PostsCollection.unshift(oPost);
			}
		}
		else
		{
			this.getChannels();
		}
	}
};

CChatView.prototype.onGetPreviousPostsResponse = function (oResponse, oRequest)
{
	if (oResponse.Result && Types.isNonEmptyArray(oResponse.Result.Collection))
	{
		var
			aPosts = oResponse.Result.Collection,
			iLastIndex = aPosts.length - 1
		;

		/**
		* Adds all new posts to the beginning of the post list.
		*/
		for (var iIndex = iLastIndex; iIndex >= 0; iIndex--)
		{
			this.addPost(aPosts[iIndex], false, aPosts[iIndex].userId === App.getUserId());
		}

		if (!HeaderItemView.isCurrent())
		{
			HeaderItemView.isUnseen(true);
		}
	}
	this.gettingMore(false);
};

/**
 * 
 */
CChatView.prototype.updateOwnPost = function (oNewPost)
{
	var
		oCahnnel = this.getChannelByUUID(oNewPost.channelUUID),
		oOldPost = _.find(oCahnnel.PostsCollection(), function(oPost){
			return oPost.GUID === oNewPost.GUID; 
		})
	;

	if (oOldPost)
	{
		oOldPost.displayDate(this.getDisplayDate(moment.utc(oNewPost.date)));
		oOldPost.displayText(oNewPost.isHtml ? oNewPost.text : TextUtils.encodeHtml(oNewPost.text));
		return true;
	}
	return false;
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
		return oLocal.format('HH:mm');
	}
	else
	{
		return oLocal.format('MMM Do HH:mm');
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
		var
			sDate = moment().utc().format('YYYY-MM-DD HH:mm:ss'),
			GUID = this.guid()
		;
		Ajax.send(
			'CreatePost',
			{
				'Text': this.replyText(),
				'ChannelUUID': this.selectedChannel().UUID,
				'GUID': GUID
			},
			function (oResponse)
			{
				if (oResponse && !oResponse.Result)
				{
					Screens.showError(TextUtils.i18n('%MODULENAME%/ERROR_POST_CREATING'));
				}
			},
			this
		);
		this.addPost({
				userId: App.getUserId(),
				name: App.getUserPublicId(),
				text: this.replyText(),
				date: sDate,
				recent: true,
				channelUUID: this.selectedChannel().UUID,
				GUID: GUID
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
	if (oResponse.Result && Types.isNonEmptyArray(oResponse.Result.Collection))
	{
		var
			aPosts = oResponse.Result.Collection,
			aPostsCount = oResponse.Result.PostsCount,
			iFirstIndex = 0
		;

		/**
		 * Adds all new posts to the end of the post list.
		 */
		for (var iIndex = iFirstIndex; iIndex < aPosts.length; iIndex++)
		{
			if (aPosts[iIndex].systemCommandCode)
			{
				this.processSystemPost(aPosts[iIndex]);
			}
			if (aPosts[iIndex].userId === App.getUserId())
			{
				if (aPosts[iIndex].GUID === '' || !this.updateOwnPost(aPosts[iIndex]))
				{
					this.addPost(aPosts[iIndex], true, true);
				}
			}
			else
			{
				this.addPost(aPosts[iIndex], true, false);
			}
		}
		this.scrollIfNecessary(500);

		_.each(this.channels(), _.bind(function (oChannel) {
			this.removeExtraPosts(oChannel.UUID);
		},this));
		if (!HeaderItemView.isCurrent())
		{
			HeaderItemView.isUnseen(true);
		}
		//update posts count information
		for (var ChannelUUID in aPostsCount)
		{
			var oChannel = this.getChannelByUUID(ChannelUUID);

			if (oChannel && oChannel.PostsCollection)
			{
				oChannel.PostsCount(aPostsCount[ChannelUUID]);
			}
		}
	}
	this.gettingMore(false);
	setTimeout(_.bind(this.getLastPosts, this),1000);
};

/**
 * Hide posts if it's count bigger then postsPerPage + offset 
 * @param {type} ChannelUUID
 * @returns {undefined}
 */
CChatView.prototype.removeExtraPosts = function (ChannelUUID)
{
	var
		oChannel = this.getChannelByUUID(ChannelUUID),
		iNumberOfExtraPosts = oChannel.PostsCollection().length - oChannel.PostsOnPage()
	;

	if (iNumberOfExtraPosts > 0)
	{
		for (var i = 0; i < iNumberOfExtraPosts; i++)
		{
			oChannel.PostsCollection.remove(oChannel.PostsCollection()[0]);
		}
		oChannel.Offset(Types.pInt(oChannel.Offset()) + iNumberOfExtraPosts);
	}
};

CChatView.prototype.setTextFocus = function ()
{
	$('#reply_text').focus();
};

CChatView.prototype.showChannel = function (UUID)
{
	this.selectedChannel(this.getChannelByUUID(UUID));
};

CChatView.prototype.initChannelsData = function (oChannelsData)
{
	for (var ChannelUUID in oChannelsData)
	{
		this.addChannelToList(ChannelUUID, oChannelsData[ChannelUUID]);
		if (!this.selectedChannel())
		{
			this.selectedChannel(this.getChannelByUUID(ChannelUUID));
		}
	}
};

CChatView.prototype.addUser = function ()
{
	Popups.showPopup(AddUserPopup, [
		//update user list after adding new user to channel
		_.bind(this.updateUserListInCurrentChannel, this),
		this.selectedChannel().UUID
	]);
};

CChatView.prototype.getChannelByUUID = function (UUID)
{
	return _.find(this.channels(), function(oChannel){
		return oChannel.UUID === UUID; 
	});
};

CChatView.prototype.removeChannelByUUID = function (UUID)
{
	this.channels(
		_.filter(this.channels(), function(oChannel){
			return oChannel.UUID !== UUID; 
		})
	);
};

CChatView.prototype.getChannels = function ()
{
	Ajax.send('GetUserChannelsWithPosts',
		{
			'Limit': 0 //need only channels list
		},
		this.onGetChannelsResponse,
		this
	);
};

CChatView.prototype.onGetChannelsResponse = function (oResponse, oRequest)
{
	var
		SelectedChannelUUID = this.selectedChannel() ? this.selectedChannel().UUID : '',
		aProcessedChannelsUUIDs = [];
	;

	if (oResponse.Result && oResponse.Result.Collection && !_.isEmpty(oResponse.Result.Collection))
	{
		this.selectedChannel(null);
		for (var ChannelUUID in oResponse.Result.Collection)
		{
			var oCurrentChannel = this.getChannelByUUID(ChannelUUID);

			aProcessedChannelsUUIDs.push(ChannelUUID);
			if (oCurrentChannel)
			{//update existing channel
				oCurrentChannel.PostsCount(oResponse.Result.Collection[ChannelUUID]['PostsCount']);
				oCurrentChannel.Name(oResponse.Result.Collection[ChannelUUID]['Name']);
			}
			else
			{
				this.addChannelToList(ChannelUUID, oResponse.Result.Collection[ChannelUUID]);
			}
			if (ChannelUUID === SelectedChannelUUID)
			{
				this.selectedChannel(this.getChannelByUUID(ChannelUUID));
			}
		}
		//deleting channels that are no longer available to the user
		_.each(this.channels(), _.bind(function (oChannel) {
			if (aProcessedChannelsUUIDs.indexOf(oChannel.UUID) === -1)
			{
				this.removeChannelByUUID(oChannel.UUID);
			}
		},this));
		//if selected channel was removed set first channel as selected
		if (!this.selectedChannel() && this.channels()[0])
		{
			this.selectedChannel(this.channels()[0]);
		}
	}
};

CChatView.prototype.addChannelToList = function (ChannelUUID, oChannelData)
{
	this.channels.push({
		'Offset': ko.observable(oChannelData['PostsCount'] > this.postsPerPage ? oChannelData['PostsCount'] - this.postsPerPage : 0),
		'PostsCount': ko.observable(oChannelData['PostsCount']),
		'Name': ko.observable(oChannelData['Name']),
		'PostsOnPage': ko.observable(this.postsPerPage),
		'PostsCollection': ko.observableArray([]),
		'UsersCollection': ko.observableArray(oChannelData['UsersCollection']),
		'UUID': ChannelUUID
	});
	_.each(oChannelData['PostsCollection'], _.bind(function (oPost) {
		this.addPost(oPost, true, oPost.userId === App.getUserId());
	}, this));
};

CChatView.prototype.createChannel = function ()
{
	var
		fOnCreateChannelCallback = _.bind(function () {
			this.getChannels(); 
		}, this)
	;
	Popups.showPopup(CreateChannelPopup, [fOnCreateChannelCallback]);
};

CChatView.prototype.editChannel = function (ChannelUUID)
{
	var
		oCahnnel = this.getChannelByUUID(ChannelUUID),
		fOnRenameChannelCallback = _.bind(function () {
			this.getChannels(); 
		}, this)
	;
	Popups.showPopup(RenameChannelPopup, [oCahnnel.UUID, oCahnnel.Name(), fOnRenameChannelCallback]);
};

CChatView.prototype.deleteUserFromChannel = function (oUser, ChannelUUID)
{
	Popups.showPopup(ConfirmPopup, [
		TextUtils.i18n('%MODULENAME%/CONFIRM_REMOVE_USER_FROM_CHANNEL'),
		_.bind(function(bConfirm) {
			if (bConfirm)
			{
				Ajax.send('DeleteUserFromChannel',
				{
					'UserPublicId': oUser.PublicId,
					'ChannelUUID': ChannelUUID
				},
				_.bind(function (oResponse) {
					var oChannel = null;
					if (oResponse.Result) 
					{
						oChannel = this.getChannelByUUID(ChannelUUID);
						//if user remove himself - remove channel from channels list
						if (oUser.PublicId === App.getUserPublicId())
						{
							this.removeChannelByUUID(ChannelUUID);
							if (this.channels()[0])
							{
								this.selectedChannel(this.channels()[0]);
							}
							else
							{
								this.selectedChannel(false);
							}
						}
						else
						{//remove user from users list
							this.updateUserListInCurrentChannel();
						}
					}
					else
					{
						Screens.showError(TextUtils.i18n('%MODULENAME%/ERROR_USER_DELETING'));
					}
				}, this),
				this
			);
			}
		}, this),
		oUser.PublicId]);
};

CChatView.prototype.processSystemPost = function (oPost)
{
	switch (oPost.systemCommandCode)
	{
		case Enums.CommandCodes.UpdateChannelsList:
			this.getChannels();
			break;
	}
};

CChatView.prototype.getSystemPostMessage = function (oPost)
{
	var
		oPostData = null,
		sMessage = ''
	;

	try
	{
		oPostData = $.parseJSON(oPost.text);
	}
	catch (oException)
	{
		oPostData = null;
	}
	if (oPostData && oPostData.Text)
	{
		sMessage = oPostData.Text
	}
	
	return sMessage;
};

CChatView.prototype.showMessageDate = function (oMessage)
{
	oMessage.hideMessageDate(false);
};

CChatView.prototype.hideMessageDate = function (oMessage)
{
	oMessage.hideMessageDate(true);
};

CChatView.prototype.updateUserListInCurrentChannel = function () 
{
	Ajax.send('GetUserChannelsWithPosts',
		{
			'Limit': this.postsPerPage
		},
		_.bind(function(oResponse) {
			if (oResponse.Result && oResponse.Result.Collection && !_.isEmpty(oResponse.Result.Collection))
			{
				for (var ChannelUUID in oResponse.Result.Collection)
				{
					if (ChannelUUID === this.selectedChannel().UUID)
					{
						this.selectedChannel().UsersCollection(oResponse.Result.Collection[ChannelUUID]['UsersCollection']);
						this.selectedChannel().Name(oResponse.Result.Collection[ChannelUUID]['Name']);
					}
				}
			}
		}, this),
		this
	);
};

CChatView.prototype.guid = function ()
{
  function s4()
  {
    return Math.floor((1 + Math.random()) * 0x10000)
      .toString(16)
      .substring(1);
  }
  return s4() + s4() + '-' + s4() + '-' + s4() + '-' + s4() + '-' + s4() + s4() + s4();
};

module.exports = new CChatView();
