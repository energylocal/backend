<?php
global $path;
$v = 1;
?>
<script src="<?php echo $path; ?>Lib/vue.min.js"></script>

<div id="app">

    <h3><?php echo $club_name; ?>: {{ account.username }}</h3>

    <h3 v-if="mode=='add'">Add account</h3>
    <h3 v-if="mode=='edit'">Edit account</h3>

    <div class="row-fluid" style="max-width:800px">
        <div class="span4">
            <p>
                <label>Username</label>
                <input type="text" v-model="account.username">
            </p>
        </div>
        <div class="span4" v-if="mode=='add'">
            <p>
                <label>Password</label>
                <input type="text" v-model="account.password">
            </p>
        </div>
        <div class="span4">
            <p>
                <label>Email</label>
                <input type="text" v-model="account.email">
            </p>
        </div>
    </div>

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

    <p><b>Tariff:</b></p>

    <div class="row-fluid" style="max-width:800px">
        <div class="span4">
            <p>
                <select v-model="account.tariff_id">
                    <option>UNASSIGNED</option>
                    <option v-for="tariff in tariffs" :value="tariff.id">{{ tariff.name }}</option>
                </select>
            </p>
        </div>
    </div>

    <a href="<?php echo $path; ?>account/list?clubid=<?php echo $clubid; ?>"><button class="btn">Cancel</button></a>
    <button class="btn btn-info" @click="save_account" v-if="mode=='add'">Add account</button>
    <button class="btn btn-warning" @click="save_account" v-if="mode=='edit'">Save changes</button>
    <button class="btn btn-success" @click="fetch_octopus_data">Fetch Octopus data</button>

    <div class="alert alert-error" style="margin-top:20px; width:440px" v-if="show_error" v-html="error_message"></div>
    <div class="alert alert-success" style="margin-top:20px; width:440px" v-if="show_success" v-html="success_message"></div>


</div>

</div>

<script>
    var clubid = <?php echo $clubid; ?>;
    var userid = <?php echo $userid; ?>;
    
    get_account();
    tariff_list();

    app = new Vue({
        el: '#app',
        data: {
            userid: <?php echo $userid; ?>,
            account: {},
            tariffs: [],
            mode: 'edit', // edit, add
            show_error: false,
            error_message: '',
            show_success: false,
            success_message: ''
        },
        methods: {
            
            save_account: function() {
                var params = {
                    'id': clubid,
                    'username': encodeURIComponent(this.account.username),
                    'email': encodeURIComponent(this.account.email),
                    'mpan': this.account.mpan,
                    'cad_serial': this.account.cad_serial,
                    'octopus_apikey': this.account.octopus_apikey,
                    'meter_serial': this.account.meter_serial
                };

                var api = "";
                if (this.mode == 'add') {
                    api = "account/add.json";
                    params["password"] = encodeURIComponent(this.edit.password);
                } else if (this.mode == 'edit') {
                    api = "account/edit.json";
                    params['userid'] = this.userid;
                }

                this.show_error = false;
                $.post('<?php echo $path; ?>' + api, params, function(result) {
                    if (result.success) {
                    
                    } else {
                        app.error_message = "<b>Error:</b> " + result.message;
                        app.show_error = true;
                    }
                });


            },
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
            },
            graph: function() {
                alert("graph");
            }
        }
    });

    function get_account() {
        $.getJSON('<?php echo $path; ?>account/get.json', {
            userid: userid
        }, function(result) {
            app.account = result;
        });
    }

    function tariff_list() {
        $.getJSON('<?php echo $path; ?>club/tariff/list.json', {
            clubid: clubid
        }, function(result) {
            app.tariffs = result;
        });
    }
</script>
