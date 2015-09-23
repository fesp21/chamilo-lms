<div id="chat-video-panel">
    <div class="alert alert-warning alert-dismissible fade in">
        <button type="button" class="close" data-dismiss="alert" aria-label="{{ 'Close'|get_lang }}">
            <span aria-hidden="true">&times;</span>
        </button>
        <h4>{{ 'Warning'|get_lang }}</h4>
        <p>
            <i class="fa fa-warning"></i> {{ 'AvoidChangingOfPageAsThisWillCutYourCurrentVideoChatSession'|get_lang }}
        </p>
    </div>
    <div class="row">
        <div class="col-md-4">
            <div class="thumbnail">
                <div id="chat-local-video"></div>
                <div class="caption">
                    <p class="text-muted text-center">{{ user_local.complete_name }}</p>
                </div>
            </div>
            <div id="connection-status"></div>
            <div class="chat-friends">
                <div class="panel-group" id="blocklistFriends" role="tablist" aria-multiselectable="true">
                    <div class="panel panel-default">
                        <div class="panel-heading" role="tab" id="headingOne">
                            <h4 class="panel-title">
                                <a role="button" data-toggle="collapse" data-parent="#blocklistFriends" href="#listFriends" aria-expanded="true" aria-controls="listFriends">
                                    {{ "SocialFriend" | get_lang }}
                                </a>
                            </h4>
                        </div>
                        <div id="listFriends" class="panel-collapse collapse in" role="tabpanel" aria-labelledby="headingOne">
                            <div class="panel-body">
                                {{ block_friends }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="thumbnail video-chat-user">
                <div id="chat-remote-video"></div>
                <div class="caption">
                    <p class="text-muted text-center">{{ "ChatWithXUser"|get_lang|format(chat_user.complete_name) }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    (function() {
        var VideoChat = {
            init: function() {
                var isCompatible = !!Modernizr.prefixed('RTCPeerConnection', window);

                var notifyNotSupport = function() {
                    $.get('{{ _p.web_ajax }}chat.ajax.php', {
                        action: 'notify_not_support',
                        to: {{ chat_user.id }}
                    });
                };

                var startVideoChat = function() {
                    var webRTC = new SimpleWebRTC({
                        localVideoEl: 'chat-local-video',
                        remoteVideosEl: '',
                        autoRequestMedia: true
                    });

                    webRTC.on('readyToCall', function() {
                        webRTC.joinRoom('{{ room_name }}');
                    });
                    webRTC.on('videoAdded', function (video, peer) {
                        $(video).addClass('skip');
                        $('#chat-remote-video').html(video);

                        if (peer && peer.pc) {
                            peer.pc.on('iceConnectionStateChange', function () {
                                var alertDiv = $('<div>')
                                    .addClass('alert');

                                switch (peer.pc.iceConnectionState) {
                                    case 'checking':
                                        alertDiv
                                            .addClass('alert-info')
                                            .html('<i class="fa fa-spinner fa-spin"></i> ' + "{{ 'ConnectingToPeer'|get_lang }}");
                                        break;
                                    case 'connected':
                                        //no break
                                    case 'completed':
                                        alertDiv
                                            .addClass('alert-success')
                                            .html('<i class="fa fa-commenting"></i> ' + "{{ 'ConnectionEstablished'|get_lang }}");
                                        break;
                                    case 'disconnected':
                                        alertDiv
                                            .addClass('alert-info')
                                            .html('<i class="fa fa-frown-o"></i> ' + "{{ 'Disconnected'|get_lang }}");
                                        break;
                                    case 'failed':
                                        alertDiv
                                            .addClass('alert-danger')
                                            .html('<i class="fa fa-times"></i> ' + "{{ 'ConnectionFailed'|get_lang }}");
                                        break;
                                    case 'closed':
                                        alertDiv
                                            .addClass('alert-danger')
                                            .html('<i class="fa fa-close"></i> ' + "{{ 'ConnectionClosed'|get_lang }}");
                                        break;
                                }

                                $('#connection-status').html(alertDiv);
                            });
                        }
                    });
                    webRTC.on('videoRemoved', function (video, peer) {
                        video.src = '';
                    });
                    webRTC.on('iceFailed', function (peer) {
                        var alertDiv = $('<div>')
                            .addClass('alert-danger')
                            .html('<i class="fa fa-close"></i> ' + "{{ 'LocalConnectionFailed'|get_lang }}");

                        $('#connection-status').html(alertDiv);
                    });
                    webRTC.on('connectivityError', function (peer) {
                        var alertDiv = $('<div>')
                            .addClass('alert-danger')
                            .html('<i class="fa fa-close"></i> ' + "{{ 'RemoteConnectionFailed'|get_lang }}");

                        $('#connection-status').html(alertDiv);
                    });
                };

                if (!isCompatible) {
                    notifyNotSupport();

                    $('#chat-video-panel').remove();
                    return;
                }

                $('#messages').remove();

                startVideoChat();

                window.onbeforeunload = function () {
                    return "{{ 'AvoidChangingOfPageAsThisWillCutYourCurrentVideoChatSession'|get_lang }}";
                };
            }
        };

        $(document).on('ready', function() {
            VideoChat.init();
        });
    })();
</script>
