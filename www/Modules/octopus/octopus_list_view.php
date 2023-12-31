<?php defined('EMONCMS_EXEC') or die('Restricted access'); ?>

<script src="<?php echo $path; ?>Lib/vue.min.js"></script>

<h3>Octopus accounts</h3>

<div id="app">

    <div class="alert alert-warning" v-if="accounts.length == 0">
        <p>Looks like no octopus accounts have been added yet.</p>
    </div>

    <table class="table table-striped">
        <tr>
            <th>Clubid</th>
            <th>Username</th>
            <th>MPAN</th>
            <th>Meter serial</th>
            <th>Octopus API key</th>
            <th>Last updated</th>
            <th></th>
            <th></th>
        </tr>
        <tr v-for="(account,index) in accounts">
            <td>{{ account.clubid }}</td> 
            <td>{{ account.username }}</td>
            <td>{{ account.mpan }}</td>
            <td>{{ account.meter_serial }}</td>
            <td>{{ account.octopus_apikey }}</td>
            <td>
            <span v-if="account.days">
                <span class="label label-success" v-if="account.updated<7" :title="account.days | toFixed(0) + ' days of data'" @click="graph(account.feedid)">
                    {{ account.updated | toFixed(0) }}d ago</span>
                <span class="label label-warning" v-else-if="account.updated<31" :title="account.days | toFixed(0) + ' days of data'" @click="graph(account.feedid)">
                    {{ account.updated | toFixed(0) }}d ago</span>
                <span class="label label-important" v-else :title="account.days | toFixed(0) + ' days of data'" @click="graph(account.feedid)">
                    {{ account.updated | toFixed(0) }}d ago</span>
            </span>
            <span v-else class="label">no data</span>
            </td>
            <td><button class="btn btn-mini btn-primary" @click="load_data(index)">Load data</button></td>
            <td><button class="btn btn-mini btn-danger" @click="remove(index)">Delete</button></td>
        </tr>
    </table>

    <h3>Add Account</h3>
    <p><b>Userid</b><br>
        <input type="text" value="" v-model="edit.userid">
    </p>

    <p><b>MPAN</b><br>
        <input type="text" value="" v-model="edit.mpan">
    </p>

    <p><b>Meter serial</b><br>
        <input type="text" value="" v-model="edit.meter_serial">
    </p>

    <p><b>Octopus API key</b><br>
        <input type="text" value="" v-model="edit.octopus_apikey">
    </p>

    <button class="btn btn-primary" @click="add">Add</button>
    <div class="alert alert-error hide" id="error" style="width:300px"></div>
</div>

<script>
    // Fetch accounts from API
    var accounts = [];
    reload_list();

    var app = new Vue({
        el: '#app',
        data: {
            accounts: accounts,
            edit: {
                userid: 0,
                mpan: "",
                meter_serial: "",
                octopus_apikey: ""
            }
        },
        methods: {
            add: function() {
                $("#error").hide();
                $.post('<?php echo $path; ?>octopus/add.json', this.edit, function(data) {
                    if (data.success) {
                        reload_list();
                    } else {
                        $("#error").html("<b>Error:</b> "+data.message).show();
                    }
                });
            },
            remove: function(index) {
                
                // confirm
                if (!confirm("Are you sure you want to delete octopus entry")) return;

                $("#error").hide();
                $.get('<?php echo $path; ?>octopus/delete.json', {userid: userid}, function(data) {
                    if (data.success) {
                        reload_list();
                    } else {
                        $("#error").html("<b>Error:</b> "+data.message).show();
                    }
                });
            },
            load_data: function(index) {
                var account = this.accounts[index];
                $.post('<?php echo $path; ?>octopus/fetch_data.json', account, function(data) {
                    if (data.success) {
                        alert(data.message);
                    } else {
                        $("#error").html("<b>Error:</b> "+data.message).show();
                    }
                });
            }
        },
        filters: {
            toFixed: function(value, decimals) {
                if (!value) value = 0;
                if (!decimals) decimals = 0;
                value = value.toFixed(decimals);
                return value;
            }
        }
    });

    function reload_list() {
        $.getJSON('<?php echo $path; ?>octopus/list.json', function(data) {
            app.accounts = data;
        });
    }
</script>
