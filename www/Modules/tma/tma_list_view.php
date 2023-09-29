<?php defined('EMONCMS_EXEC') or die('Restricted access'); ?>

<script src="<?php echo $path; ?>Lib/vue.min.js"></script>

<h3>TMA accounts</h3>

<div id="app">

    <div class="alert alert-warning" v-if="accounts.length == 0">
        <p>Looks like no TMA accounts have been added yet.</p>
    </div>

    <table class="table table-striped">
        <tr>
            <th>Clubid</th>
            <th>Username</th>
            <th>MPAN</th>
            <th>Last updated</th>
            <th></th>
            <th></th>
        </tr>
        <tr v-for="(account,index) in accounts">
            <td>{{ account.clubid }}</td> 
            <td>{{ account.username }}</td>

            <!-- list MPAN and display MPAN in green if it is in the mpan_list -->
            <td>
                <span v-if="mpan_list.includes(1*account.mpan)" style="color:green">{{ account.mpan }}</span>
                <span v-else>{{ account.mpan }}</span>
            </td>
            
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
            <td>
                <button class="btn btn-mini btn-primary" @click="load_data(index)">Load data</button>
                <button class="btn btn-mini btn-danger" @click="remove(index)">Delete</button>
            </td>
        </tr>
    </table>

    <h3>Add Account</h3>
    <p><b>Userid</b><br>
        <input type="text" value="" v-model="edit.userid">
    </p>

    <p><b>MPAN</b><br>
        <!-- Auto complete from MPAN list -->
        <select v-model="edit.mpan">
            <option value="">Select MPAN</option>
            <option v-for="mpan in mpan_list" :value="mpan">{{ mpan }}</option>
        </select>
    </p>

    <button class="btn btn-primary" @click="add">Add</button>
    <div class="alert alert-error hide" id="error" style="width:300px"></div>
    <br><br>
    <button class="btn btn-warning" @click="load_from_ftp">Load from FTP</button>
</div>

<script>
    // Fetch accounts from API
    var accounts = [];
    var mpan_list = [];
    reload_list();
    load_mpan_list();

    var app = new Vue({
        el: '#app',
        data: {
            accounts: accounts,
            mpan_list: mpan_list,
            edit: {
                userid: 0,
                mpan: ""
            }
        },
        methods: {
            add: function() {
                $("#error").hide();
                $.post('<?php echo $path; ?>tma/add.json', this.edit, function(data) {
                    if (data.success) {
                        reload_list();
                    } else {
                        $("#error").html("<b>Error:</b> "+data.message).show();
                    }
                });
            },
            remove: function(index) {
                
                // confirm
                if (!confirm("Are you sure you want to delete TMA entry")) return;

                var userid = this.accounts[index].userid;

                $("#error").hide();
                $.get('<?php echo $path; ?>tma/delete.json', {userid: userid}, function(data) {
                    if (data.success) {
                        reload_list();
                    } else {
                        $("#error").html("<b>Error:</b> "+data.message).show();
                    }
                });
            },
            load_data: function(index) {
                var userid = this.accounts[index].userid;

                $.get('<?php echo $path; ?>tma/fetch_data.json', {userid: userid}, function(data) {
                    if (data.success) {
                        alert(data.message);
                    } else {
                        $("#error").html("<b>Error:</b> "+data.message).show();
                    }
                });
            },
            load_from_ftp: function() {
                $.post('<?php echo $path; ?>tma/load_from_ftp.json', {}, function(data) {
                    if (data.success) {
                        alert(data.message);
                    } else {
                        $("#error").html("<b>Error:</b> "+data.message).show();
                    }
                });
            },
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
        $.getJSON('<?php echo $path; ?>tma/list.json', function(data) {
            app.accounts = data;
        });
    }

    // Load available MPAN list
    function load_mpan_list() {
        $.getJSON('<?php echo $path; ?>tma/mpan_list.json', function(data) {
            app.mpan_list = data;
        });
    }
</script>
