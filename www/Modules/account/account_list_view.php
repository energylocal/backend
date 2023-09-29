<?php
defined('EMONCMS_EXEC') or die('Restricted access');

global $path;
$v = 1;
?>
<script src="<?php echo $path; ?>Lib/vue.min.js"></script>

<div id="app">

    <h3><?php echo $club_name; ?>: Accounts</h3>

    <a href="<?php echo $path; ?>account/add?clubid=<?php echo $clubid; ?>">
        <button class="btn" style="float: right;"><i class="icon-plus"></i> Add new user</button>
    </a>

    <ul class="nav nav-tabs">
        <li class="active"><a href="<?php echo $path; ?>account/list?clubid=<?php echo $clubid; ?>">Accounts</a></li>
        <li><a href="<?php echo $path; ?>tariff/list?clubid=<?php echo $clubid; ?>">Tariffs</a></li>
    </ul>


    <table class="table table-striped">
        <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Email</th>
            <th>Tariff</th>
            <!--
            <th>MPAN</th>
            <th>CAD</th>
            <th>Octopus</th>
            <th>Meter Serial</th>
            <th>Data</th>
            <th>Enabled</th>
            -->
        </tr>
        <tr v-for="account,index in accounts" @click="edit_account(index)">
            <td><a :href="'<?php echo $path; ?>account/edit?userid='+account.userid">{{ account.userid }}</a></td>
            <td>{{ account.username }}</td>
            <td>{{ account.email }}</td>
            <td>{{ account.tariff_name }}</td>
            <!--<td>{{ account.mpan }}</td>
            <td>{{ account.cad_serial }}</td>
            <td>{{ account.octopus_apikey }}</td>
            <td>{{ account.meter_serial }}</td>-->

            <!--
            <td v-for="source in data_status[account.userid]">
                <span v-if="source.days">
                    <span class="label label-success" v-if="source.updated<7" :title="source.days | toFixed(0) + ' days of data'" @click="graph(source.feedid)">
                        {{ source.updated | toFixed(0) }}d ago</span>
                    <span class="label label-warning" v-else-if="source.updated<31" :title="source.days | toFixed(0) + ' days of data'" @click="graph(source.feedid)">
                        {{ source.updated | toFixed(0) }}d ago</span>
                    <span class="label label-important" v-else :title="source.days | toFixed(0) + ' days of data'" @click="graph(source.feedid)">
                        {{ source.updated | toFixed(0) }}d ago</span>
                </span>
                <span v-else class="label">no data</span>
            </td>
            <td v-if="!data_status[account.userid]"><span class="label">loading</span></td>
            
            <td>{{ account.enabled }}</td>
            -->
        </tr>
    </table>
</div>

</div>

<script>
    var clubid = <?php echo $clubid; ?>;
    
    account_list();
    //data_status();

    app = new Vue({
        el: '#app',
        data: {
            accounts: [],
            //data_status: [],
            show_error: false,
            error_message: '',
            show_success: false,
            success_message: ''
        },
        filters: {
            toFixed: function(value, decimals) {
                if (!value) value = 0;
                if (!decimals) decimals = 0;
                value = value.toFixed(decimals);
                return value;
            }
        },
        methods: {

        }
    });

    function account_list() {
        $.getJSON('<?php echo $path; ?>account/list.json', {
            clubid: clubid
        }, function(result) {
            app.accounts = result;
        });
    }

    /*
    function data_status() {
        $.getJSON('<?php echo $path; ?>account/data-status.json', {
            clubid: clubid
        }, function(result) {
            app.data_status = result;
        });
    }*/
</script>
