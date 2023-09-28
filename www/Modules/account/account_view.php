<?php
global $path;
$v = 1;
?>
<script src="<?php echo $path; ?>Lib/vue.min.js"></script>

<div id="app">

    <!--<h3><?php echo $club_name; ?>: {{ account.username }}</h3>-->

    <h3 v-if="mode=='add'">Add account</h3>
    <h3 v-if="mode=='edit'">Edit account</h3>

    <div class="row">
        <div class="span3">
            <p>
                <label>Club</label>
                <input type="text" v-model="club_name" disabled>
            </p>
        </div>
        <div class="span3">
            <p>
                <label>Username</label>
                <input type="text" v-model="account.username" @change="update">
            </p>
        </div>
        <div class="span3" v-if="mode=='add'">
            <p>
                <label>Password</label>
                <input type="text" v-model="account.password" @change="update">
            </p>
        </div>
        <div class="span3">
            <p>
                <label>Email</label>
                <input type="text" v-model="account.email" @change="update">
            </p>
        </div>
    </div>

    <!--
    <p><b>Data sources:</b></p>

    <div class="row-fluid" style="max-width:800px">
        <div class="span4">
            <p>
                <label>MPAN</label>
                <input type="text" v-model="account.mpan">
            </p>
        </div>
        <div class="span4">
            <p>
                <label>Meter serial</label>
                <input type="text" v-model="account.meter_serial">
            </p>
        </div>
        <div class="span4">
            <p>
                <label>Octopus API key</label>
                <input type="text" v-model="account.octopus_apikey">
            </p>
        </div>

    </div>

    <div class="row-fluid" style="max-width:800px">
    <div class="span4">
            <p>
                <label>CAD Serial</label>
                <input type="text" v-model="account.cad_serial">
            </p>
        </div>
    </div>
    
-->
    <p><b>Tariff:</b></p>

    <div class="row-fluid" style="max-width:800px">
        <div class="span4">
            <p>
                <select v-model="account.tariff_id" @change="update">
                    <option>UNASSIGNED</option>
                    <option v-for="tariff in tariffs" :value="tariff.id">{{ tariff.name }}</option>
                </select>
            </p>
        </div>
    </div>

    <p><b>Tariff history</b></p>
    <table class="table table-striped">
        <tr>
            <th>Tariff ID</th>
            <th>Start date</th>
        </tr>
        <tr v-for="tariff in tariff_history">
            <td>{{ tariff.tariff_name }}</td>
            <td>{{ tariff.start }}</td>
        </tr>
    </table>

    <a href="<?php echo $path; ?>account/list?clubid=<?php echo $clubid; ?>"><button class="btn">Account list</button></a>
    <button class="btn btn-info" @click="save_account" v-if="mode=='add'">Add account</button>
    <button class="btn btn-warning" @click="save_account" v-if="mode=='edit' && changed">Save changes</button>
    <!--<button class="btn btn-success" @click="fetch_octopus_data">Fetch Octopus data</button>-->

    <div class="alert alert-error" style="margin-top:20px; width:440px" v-if="show_error" v-html="error_message"></div>
    <div class="alert alert-success" style="margin-top:20px; width:440px" v-if="show_success" v-html="success_message"></div>


</div>

</div>

<script>
    var clubid = <?php echo $clubid; ?>;
    var userid = <?php echo $userid; ?>;
    var mode = "<?php echo $mode; ?>";

    if (mode=='edit') {
        get_account(userid);
        get_user_tariff_history(userid);
    }
    
    tariff_list();

    app = new Vue({
        el: '#app',
        data: {
            mode: "<?php echo $mode; ?>",
            userid: <?php echo $userid; ?>,
            club_name: "<?php echo $club_name; ?>",

            account: {
                username: '',
                password: '',
                email: '',
                tariff_id: 'UNASSIGNED'
            },
            tariffs: [],
            tariff_history: [],

            changed: false,
            show_error: false,
            error_message: '',
            show_success: false,
            success_message: ''
        },
        methods: {
            update: function () {
                this.changed = true;
            },
            
            save_account: function() {
                var params = {
                    'clubid': clubid,
                    'username': encodeURIComponent(this.account.username),
                    'email': encodeURIComponent(this.account.email)
                };

                var api = "";
                if (this.mode == 'add') {
                    api = "account/add.json";
                    params["password"] = encodeURIComponent(this.account.password);
                } else if (this.mode == 'edit') {
                    api = "account/edit.json";
                    params['userid'] = this.userid;
                }

                this.show_error = false;
                $.post('<?php echo $path; ?>' + api, params, function(result) {
                    if (result.success) {
                        app.show_success = true;
                        app.success_message = "Account saved";
                        app.changed = false;
                    } else {
                        app.error_message = "<b>Error:</b> " + result.message;
                        app.show_error = true;
                    }
                });

                // save tariff
                if (this.account.tariff_id != 'UNASSIGNED') {
                    save_user_tariff(this.userid,this.account.tariff_id);
                }
            },
            /*
            fetch_octopus_data: function() {
                app.show_success = true;
                app.success_message = "Fetching data...";
                this.show_error = false;
                $.post('<?php echo $path; ?>octopus/fetch_data.json', {
                    'userid': this.userid,
                    'mpan': this.account.mpan,
                    'octopus_apikey': this.account.octopus_apikey,
                    'meter_serial': this.account.meter_serial
                }, function(result) {
                    if (result.success) {
                        app.show_error = false;
                        app.show_success = true;
                        app.success_message = result.message;
                    } else {
                        app.show_success = false;
                        app.show_error = true;
                        app.error_message = "<b>Error:</b> " + result.message;
                    }
                });
            },*/
            graph: function() {
                alert("graph");
            }
        }
    });

    function get_account(userid) {
        $.getJSON('<?php echo $path; ?>account/get.json', {
            userid: userid
        }, function(result) {
            app.account = result;
            if (!app.account.tariff_id) {
                app.account.tariff_id = 'UNASSIGNED';
            }
        });
    }

    function get_user_tariff_history(userid) {
        $.getJSON('<?php echo $path; ?>tariff/user/history.json', {
            userid: userid
        }, function(result) {
            app.tariff_history = result;
        });
    }

    function tariff_list() {
        $.getJSON('<?php echo $path; ?>tariff/list.json', {
            clubid: clubid
        }, function(result) {
            app.tariffs = result;
        });
    }

    function save_user_tariff(userid,tariffid) {
        $.getJSON('<?php echo $path; ?>tariff/user/set', {
            userid: userid,
            tariffid: tariffid
        }, function(result) {
            app.tariffs = result;
        });       
    }
</script>
