<?php
global $path;
$v = 1;
?>
<script src="<?php echo $path; ?>Lib/vue.min.js"></script>

<div id="app">

    <h3>EnergyLocal</h3>

    <ul class="breadcrumb">
        <li><a href="<?php echo $path; ?>club/list">Clubs</a> <span class="divider">/</span></li>
        <li class="active"><?php echo $club_name; ?> <span class="divider">/</span></li>
        <li class="active">Accounts</li>
    </ul>
    
    <div v-if="mode=='list'">

        <ul class="nav nav-tabs">
            <li class="active"><a href="<?php echo $path; ?>club/accounts?clubid=<?php echo $clubid; ?>">Accounts</a></li>
            <li><a href="<?php echo $path; ?>club/tariffs?clubid=<?php echo $clubid; ?>">Tariffs</a></li>
        </ul>

        <p>
        <button class="btn" style="float: right;" @click="add_account"><i class="icon-plus"></i> Add new user</button>
        <div style="clear: both;"></div>
        </p>
        
        <table class="table table-striped">
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Email</th>
                <th>Tariff</th>
                <!--<th>MPAN</th>
                <th>CAD</th>
                <th>Octopus</th>
                <th>Meter Serial</th>-->
                <th>Data</th>
                <th>Enabled</th>
            </tr>
            <tr v-for="account,index in accounts" @click="edit_account(index)">
                <td><a href="#">{{ account.userid }}</a></td>
                <td>{{ account.username }}</td>
                <td>{{ account.email }}</td>
                <td>{{ account.tariff_name }}</td>
                <!--<td>{{ account.mpan }}</td>
                <td>{{ account.cad_serial }}</td>
                <td>{{ account.octopus_apikey }}</td>
                <td>{{ account.meter_serial }}</td>-->
                <td>{{ account.data }}</td>
                <td>{{ account.enabled }}</td>
            </tr>
        </table>
    </div>
    <div v-else>

        <h3 v-if="mode=='add'">Add account</h3>
        <h3 v-if="mode=='edit'">Edit account</h3>

        <div class="row-fluid" style="max-width:800px">
            <div class="span4">
                <p>
                    <label>Username</label>
                    <input type="text" v-model="edit.username">
                </p>
            </div>
            <div class="span4" v-if="mode=='add'">
                <p>
                    <label>Password</label>
                    <input type="text" v-model="edit.password" >
                </p>
            </div>
            <div class="span4">
                <p>
                    <label>Email</label>
                    <input type="text" v-model="edit.email" >
                </p>
            </div>
        </div>

        <p><b>Data sources:</b></p>

        <div class="row-fluid" style="max-width:800px">
            <div class="span4">
                <p>
                    <label>MPAN</label>
                    <input type="text" v-model="edit.mpan">
                </p>
            </div>
            <div class="span4">
                <p>
                    <label>CAD Serial</label>
                    <input type="text" v-model="edit.cad_serial" >
                </p>
            </div>
        </div>

        <div class="row-fluid" style="max-width:800px">
            <div class="span4">
                <p>
                    <label>Octopus API key</label>
                    <input type="text" v-model="edit.octopus_apikey" >
                </p>
            </div>
            <div class="span4">
                <p>
                    <label>Meter serial</label>
                    <input type="text" v-model="edit.meter_serial" >
                </p>
            </div>
        </div>

        <p><b>Tariff:</b></p>

        <div class="row-fluid" style="max-width:800px">
            <div class="span4">
                <p>
                    <select v-model="edit.tariff_id">
                        <option>UNASSIGNED</option>
                        <option v-for="tariff in tariffs" :value="tariff.id">{{ tariff.name }}</option>
                    </select>
                </p>
            </div>
        </div>

        <button class="btn" @click="mode = 'list'">Cancel</button>
        <button class="btn btn-info" @click="save_account" v-if="mode=='add'">Add account</button>
        <button class="btn btn-warning" @click="save_account" v-if="mode=='edit'">Save changes</button>
        <button class="btn btn-success" @click="fetch_octopus_data">Fetch Octopus data</button>

        <div class="alert alert-error" style="margin-top:20px; width:440px" v-if="show_error" v-html="error_message"></div>
        <div class="alert alert-success" style="margin-top:20px; width:440px" v-if="show_success" v-html="success_message"></div>

    </div>

    </div>

</div>

<script>
    var clubid = <?php echo $clubid; ?>;
    var accounts = [];
    account_list();
    tariff_list();

    app = new Vue({
        el: '#app',
        data: {
            accounts: [],
            tariffs: [],
            mode: 'list', // list, edit, add
            edit: {},
            show_error: false,
            error_message: '',
            show_success: false,
            success_message: ''
        },
        methods: {
            add_account: function () {
                this.mode = 'add';
                this.edit = {
                    username: '',
                    password: '',
                    email: '',
                    mpan: '',
                    cad_serial: '',
                    octopus_apikey: '',
                    meter_serial: ''
                };
            },
            edit_account: function (index) {
                this.mode = 'edit';
                this.edit = this.accounts[index];
            },
            save_account: function () {
                var params = {
                    'id': clubid,
                    'username': encodeURIComponent(this.edit.username),
                    'email': encodeURIComponent(this.edit.email),
                    'mpan': this.edit.mpan,
                    'cad_serial': this.edit.cad_serial,
                    'octopus_apikey': this.edit.octopus_apikey,
                    'meter_serial': this.edit.meter_serial
                };

                var api = "";
                if (this.mode == 'add') {
                    api = "club/account/add.json";
                    params["password"] = encodeURIComponent(this.edit.password);
                } else if (this.mode == 'edit') {
                    api = "club/account/edit.json";
                    params['userid'] = this.edit.userid;
                }

                this.show_error = false;
                $.post('<?php echo $path; ?>' + api, params, function (result) {
                    if (result.success) {
                        account_list();
                        app.mode = 'list';
                    } else {
                        app.error_message = "<b>Error:</b> "+result.message;
                        app.show_error = true;
                    }
                });

                
            },
            fetch_octopus_data: function () {
                app.show_success = true;
                app.success_message = "Fetching data...";
                this.show_error = false;
                $.post('<?php echo $path; ?>club/account/fetch_octopus_data.json', {
                    'userid': this.edit.userid,
                    'mpan': this.edit.mpan,
                    'octopus_apikey': this.edit.octopus_apikey,
                    'meter_serial': this.edit.meter_serial
                }, function (result) {
                    if (result.success) {
                        app.show_error = false;
                        app.show_success = true;
                        app.success_message = result.message;
                    } else {
                        app.show_success = false;
                        app.show_error = true;
                        app.error_message = "<b>Error:</b> "+result.message;
                    }      
                });
            }
        }
    });

    function account_list() {
        $.getJSON('<?php echo $path; ?>club/account/list.json', {id:clubid}, function(result) {
            app.accounts = result;
        });
    }

    function tariff_list() {
        $.getJSON('<?php echo $path; ?>club/tariff/list.json', {clubid:clubid}, function(result) {
            app.tariffs = result;
        });  
    }
</script>
