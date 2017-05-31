<!doctype html>

<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Stats</title>
    <link href="https://unpkg.com/ace-css/css/ace.min.css" rel="stylesheet">
    <style>
        .container{
            width:797px;
            margin: 30px auto;
            text-align:center
        }
    </style>
</head>
<body>

<div class="container">
    <h1 class="caps">Stats</h1>
    <h2 id="last_update" class="h5"></h2>
</div>

<div class="container">
    <div class="mb3">
        <?php foreach ($periods as $period) { ?>
            <a href="#" class="timeBtn btn <?= ($period === $periods[0] ? 'btn-primary' : '') ?>" data-time="<?= $period ?>"><?= $period ?></a>
        <?php } ?>
    </div>

    <?php foreach ($graphs as $name => $graph) { ?>
    <img id="<?= $name ?>" src="img/<?= $graph ?>hour.png" /><br><br>
    <?php } ?>
</div>

<script>
    var lastUpdate = document.getElementById('last_update');
    lastUpdate.textContent = getTimestamp().date;

    <?php foreach ($graphs as $name => $graph) { ?>

    <?php } ?>

    var timeButtons = document.querySelectorAll('.timeBtn');
    var nginxRequests = document.querySelector('#nginxRequests');
    var nginxConnections = document.querySelector('#nginxConnections');
    var cpuUsage = document.querySelector('#cpuUsage');
    var networkTraffic = document.querySelector('#networkTraffic');
    var memoryUsage = document.querySelector('#memoryUsage');
    var diskUtilization = document.querySelector('#diskUtilization');
    var diskConsumption = document.querySelector('#diskConsumption');

    for (var i = 0; i < timeButtons.length; i++) {
        timeButtons[i].addEventListener('click', function (e) {
            e.preventDefault();
            console.log(this.dataset.time);
            for (var n = 0; n < timeButtons.length; n++) {
                timeButtons[n].classList.remove('btn-primary');
            }

            this.classList.add('btn-primary');
            nginxRequests.src = 'img/requests_' + this.dataset.time + '.png';
            nginxConnections.src = 'img/connections_' + this.dataset.time + '.png';
            cpuUsage.src = 'img/cpu_usage_' + this.dataset.time + '.png';
            networkTraffic.src = 'img/network_' + this.dataset.time + '.png';
            memoryUsage.src = 'img/memory_usage_' + this.dataset.time + '.png';
            diskUtilization.src = 'img/disk_usage_' + this.dataset.time + '.png';
            diskConsumption.src = 'img/disk_consumption_' + this.dataset.time + '.png';
        });
    }
</script>
</body>
</html>
