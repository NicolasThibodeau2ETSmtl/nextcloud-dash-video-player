<?php
style("dashvideoplayer", "player");
//script("dashvideoplayer", "dash.all.min");
script("dashvideoplayer", "shaka-player.compiled.min");
?>

<div id="app-content">
    <!--<video data-dashjs-player src="<?php p($_["videoUrl"]) ?>" controls="true"></video>-->
    <video id="video" width="640" height="480" crossorigin="anonymous" controls>
        Your browser does not support HTML5 video.
    </video>
</div>

<script>
    function initPlayer() {
        // Install polyfills.
        shaka.polyfill.installAll();

        // Find the video element.
        var video = document.getElementById('video');

        // Construct a Player to wrap around it.
        var player = new shaka.player.Player(video);

        // Attach the player to the window so that it can be easily debugged.
        window.player = player;

        // Listen for errors from the Player.
        player.addEventListener('error', function(event) {
            console.error(event);
        });

        // Construct a DashVideoSource to represent the DASH manifest.
        var mpdUrl = 'https://turtle-tube.appspot.com/t/t2/dash.mpd';
        var estimator = new shaka.util.EWMABandwidthEstimator();
        var source = new shaka.player.DashVideoSource(mpdUrl, null, estimator);

        // Load the source into the Player.
        player.load(source);
    }
    document.addEventListener('DOMContentLoaded', initPlayer);
</script>