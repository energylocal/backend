<?php
global $path;
$v = 1;
?>
<script src="<?php echo $path; ?>Lib/vue.min.js"></script>
<script src="https://unpkg.com/axios/dist/axios.min.js"></script>

<div id="app">

	<h3>EnergyLocal</h3>

	<ul class="breadcrumb">
		<li><a href="<?php echo $path; ?>club/list">Clubs</a> <span class="divider">/</span></li>
        <li class="active"><?php echo $club_name; ?> <span class="divider">/</span></li>
        <li class="active">Tariffs</li>
	</ul>

	<ul class="nav nav-tabs">
		<li><a href="<?php echo $path; ?>club/accounts?clubid=<?php echo $clubid; ?>">Accounts</a></li>
		<li class="active"><a href="<?php echo $path; ?>club/tariffs?clubid=<?php echo $clubid; ?>">Tariffs</a></li>
	</ul>

	<div class="input-prepend input-append">
		<span class="add-on">New tariff</span>
		<input type="text" value="" v-model="new_tariff_name">
		<button class="btn" style="float: right;" @click="add_tariff"><i class="icon-plus"></i> Add</button>
	</div>

	<table class="table table-striped">
		<tr>
			<th>ID</th>
			<th>Name</th>
			<th>Created</th>
			<th>First assigned</th>
			<th>Last used</th>
			<th>Active users</th>
			<th>Actions</th>
		</tr>
		<tr v-for="(tariff,index) in tariffs">
			<td>{{tariff.id}}</td>
			<td>{{ tariff.name }}</td>
			<td>{{tariff.created}}</th>
			<td>{{tariff.first_assigned}}</th>
			<td>{{tariff.last_assigned}}</td>
			<td>{{tariff.active_users}}</td>
			<td>
				<button class="btn" @click="delete_tariff(tariff.id)"><i class="icon-trash"></i></button>
				<button class="btn" @click="edit_tariff(index)"><i class="icon-pencil"></i></button>
			</td>
		</tr>
	</table>

	<div v-if="selected_tariff!==false">

		<div class="input-prepend">
			<span class="add-on">Tariff name</span>
			<input type="text" v-model="tariffs[selected_tariff].name">
		</div>

		<!--
		<table class="table table-bordered">
			<tr>
				<th>Created</th>
				<th>First assigned</th>
				<th>Last used</th>
				<th>Active users</th>
			<tr>
				<td>{{tariffs[selected_tariff].created}}</th>
				<td>{{tariffs[selected_tariff].first_assigned}}</th>
				<td>{{tariffs[selected_tariff].last_assigned}}</th>
				<td>12</td>
			</tr>
		</table>
		-->
		
		<table class="table table-striped">
			<tr>
				<th>Index</th>
				<th>Name</th>
				<th>Start</th>
				<th>End</th>
				<th>Generator</th>
				<th>Import</th>
				<th>Colour</th>
				<th>Actions</th>
			</tr>
			<tr v-for="(period,index) in tariff_periods">
				<td>{{index}}</td>
				<td>
					<span v-if="edit_period_index===index">
						<input type="text" v-model="tariff_periods[edit_period_index].name" style="width:80px">
					</span>
					<span v-else>{{ period.name }}</span>		
				</td>
				<td>
					<span v-if="edit_period_index===index">
						<input type="text" v-model="tariff_periods[edit_period_index].start" style="width:80px">
					</span>
					<span v-else>{{ period.start }}</span>
				</td>
				<td>
					<span v-if="edit_period_index===index">
						<input type="text" v-model="tariff_periods[edit_period_index].end" style="width:80px">
					</span>
					<span v-else>{{ period.end }}</span>
				</td>
				<td>
					<span v-if="edit_period_index===index">
						<div class="input-append">
							<input type="text" v-model="tariff_periods[edit_period_index].generator" style="width:50px">
							<span class="add-on">p/kWh</span>
						</div>
					</span>
					<span v-else>{{ period.generator }} p/kWh</span>
				</td>
				<td>
					<span v-if="edit_period_index===index">
						<div class="input-append">
							<input type="text" v-model="tariff_periods[edit_period_index].import" style="width:50px">
							<span class="add-on">p/kWh</span>
						</div>
					</span>
					<span v-else>{{ period.import }} p/kWh</span>
				</td>
				<td>
				<input type="color" v-model="period.color" :disabled="edit_period_index===false" style="width:80px" />
				</td>
				<td>
					<button class="btn" @click="delete_period(index)" v-if="edit_period_index===false"><i class="icon-trash"></i></button>
					<button class="btn" @click="edit_period(index)" v-if="edit_period_index===false"><i class="icon-pencil"></i></button>
					<button class="btn" @click="save_period(index)" v-if="edit_period_index===index"><i class="icon-ok"></i></button>
				</td>
			</tr>
		</table>
		<!-- add new period -->
		<button class="btn" @click="add_period"><i class="icon-plus"></i> Add period</button>
	</div>
</div>

<script>

	var clubid = <?php echo $clubid; ?>;

	app = new Vue({
		el: '#app',
		data: {
			groups: false,
			selected_group: 0,
			tariffs: [],
			new_tariff_name: "",
			selected_tariff: false,
			tariff_periods: [],
			edit_period_index: false
		},
		methods: {
			add_tariff: function() {
				// url encode post body
				// club/tariff/create
				$.post(path+"club/tariff/create", {
						club: clubid,
						name: app.new_tariff_name
					})
					.done(function(data) {
						if (data.success) {
							app.list_tariffs();
						} else {
							alert("Error: "+data.message);
						}
					});
			},
			list_tariffs: function() {
				$.get(path+"club/tariff/list?clubid="+clubid)
					.done(function(data) {
						app.tariffs = data;
					});
			},
			delete_tariff: function(id) {
				if (confirm("Are you sure you want to delete this tariff?")) {
					$.get(path+"club/tariff/delete", {
							id: id
						})
						.done(function(data) {
							if (data.success) {
								app.list_tariffs();
							} else {
								alert("Error: "+data.message);
							}
						});
				}
			},
			edit_tariff: function(index) {
				app.selected_tariff = index;

				// get tariff periods
				$.get(path+"club/tariff/periods", {
						id: app.tariffs[index].id
					})
					.done(function(data) {
						app.tariff_periods = data;
					});
			},
			add_period: function() {
				// add new period to end of list

				// Default names
				var names = ["Morning", "Midday", "Evening", "Overnight"];
				var name = "Period "+(app.tariff_periods.length+1);
				if (names[app.tariff_periods.length]!=undefined) {
					name = names[app.tariff_periods.length];
				}

				// Default start times
				var starts = [6, 12, 18, 0];
				var start = 0;
				if (starts[app.tariff_periods.length]!=undefined) {
					start = starts[app.tariff_periods.length];
				}

				// Default end times
				var ends = [12, 18, 24, 6];
				var end = 0;
				if (ends[app.tariff_periods.length]!=undefined) {
					end = ends[app.tariff_periods.length];
				}

				// Default colours
				var colours = ["#ffdc00", "#ffb401", "#e6602b", "#014c2d"];
				var colour = "#000000";
				if (colours[app.tariff_periods.length]!=undefined) {
					colour = colours[app.tariff_periods.length];
				}

				var period = {
					tariffid: app.tariffs[app.selected_tariff].id,
					name: name,
					start: start,
					end: end,
					generator: 10,
					import: 20,
					color: colour
				};
				app.tariff_periods.push(period);

				$.post(path+"club/tariff/addperiod", period)
					.done(function(data) {
						if (data.success) {
							app.edit_tariff(app.selected_tariff);
						} else {
							alert("Error: "+data.message);
						}
					});
			},
			edit_period: function(index) {
				app.edit_period_index = index;
			},
			save_period: function(index) {
				app.edit_period_index = false;
				// save period
				$.post(path+"club/tariff/saveperiod", app.tariff_periods[index])
					.done(function(data) {
						if (data.success) {
							app.edit_tariff(app.selected_tariff);
						} else {
							alert("Error: "+data.message);
						}
					});
			},
			delete_period: function(index) {
				if (confirm("Are you sure you want to delete this period?")) {
					$.get(path+"club/tariff/deleteperiod", {
							tariffid: app.tariff_periods[index].tariffid,
							index: app.tariff_periods[index].index
						})
						.done(function(data) {
							if (data.success) {
								app.edit_tariff(app.selected_tariff);
							} else {
								alert("Error: "+data.message);
							}
						});
				}
			}
		},
		filters: {
			toFixed: function(value, dp) {
				return value.toFixed(dp);
			}
		}
	});

	// get list of tariffs
	app.list_tariffs();
</script>