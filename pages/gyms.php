<?php
include './vendor/autoload.php';
include './config.php';
include './includes/DbConnector.php';
include './includes/GeofenceService.php';
include './includes/utils.php';
include './static/data/pokedex.php';

$geofence_srvc = new GeofenceService();

$filters = "
<div class='container'>
  <div class='row'>
    <div class='input-group mb-3'>
      <div class='input-group-prepend'>
        <label class='input-group-text' for='filter-team'>Team</label>
      </div>
      <select id='filter-team' class='custom-select' onchange='filter_gyms()'>
        <option selected>Select</option>
        <option value='all'>All</option>
        <option value='Neutral'>Neutral</option>
        <option value='Mystic'>Mystic</option>
        <option value='Valor'>Valor</option>
        <option value='Instinct'>Instinct</option>
      </select>
    </div>
    <div class='input-group mb-3'>
      <div class='input-group-prepend'>
        <label class='input-group-text' for='filter-slots'>Available Slots</label>
      </div>
      <select id='filter-slots' class='custom-select' onchange='filter_gyms()'>
        <option disabled selected>Select</option>
        <option value='all'>All</option>
        <option value='full'>Full</option>
        <option value='1'>1</option>
        <option value='2'>2</option>
        <option value='3'>3</option>
        <option value='4'>4</option>
        <option value='5'>5</option>
      </select>
    </div>
    <div class='input-group mb-3'>
      <div class='input-group-prepend'>
        <label class='input-group-text' for='filter-battle'>In Battle Status</label>
      </div>
      <select id='filter-battle' class='custom-select' onchange='filter_gyms()'>
  	    <option disabled selected>Select</option>
   	    <option value='all'>All</option>
   	    <option value='Under Attack!'>Yes</option>
   	    <option value='Safe'>No</option>
      </select>
    </div>
    <div class='input-group mb-3'>
      <div class='input-group-prepend'>
        <label class='input-group-text' for='filter-city'>City</label>
      </div>
      <select id='filter-city' class='custom-select' onchange='filter_gyms()'>
        <option disabled selected>Select</option>
        <option value='all'>All</option>
        <option value='" . $unknown_value . "'>" . $unknown_value . "</option>";
        $count = count($geofence_srvc->geofences);
        for ($i = 0; $i < $count; $i++) {
          $geofence = $geofence_srvc->geofences[$i];
          $filters .= "<option value='".$geofence->name."'>".$geofence->name."</option>";
        }
        $filters .= "
      </select>
    </div>
  </div>
</div>
";

$modal = "
<button type='button' class='btn btn-dark float-right' data-toggle='modal' data-target='#filtersModal'>
  Filters
</button>
<div class='modal fade' id='filtersModal' tabindex='-1' role='dialog' aria-labelledby='filtersModalLabel' aria-hidden='true'>
  <div class='modal-dialog' role='document'>
    <div class='modal-content'>
      <div class='modal-header'>
        <h5 class='modal-title' id='filtersModalLabel'>Gym Filters</h5>
        <button type='button' class='close' data-dismiss='modal' aria-label='Close'>
          <span aria-hidden='true'>&times;</span>
        </button>
      </div>
      <div class='modal-body'>" . $filters . "</div>
      <div class='modal-footer'>
        <button type='button' class='btn btn-primary' data-dismiss='modal'>Close</button>
      </div>
    </div>
  </div>
</div>
";

// Establish connection to database
$db = new DbConnector($dbhost, $dbPort, $dbuser, $dbpass, $dbname);
$pdo = $db->getConnection();

// Query Database and Build Raid Billboard
try {
  $sql = "
SELECT 
  lat, 
  lon,
  guarding_pokemon_id,
  availble_slots,
  team_id,
  in_battle,
  name,
  updated
FROM 
  " . $dbname . ".gym
WHERE
  name IS NOT NULL &&
  enabled=1;
";

  $result = $pdo->query($sql);
  if ($result->rowCount() > 0) {
    echo $modal;
    echo "<div class='table-responsive'>";
    echo "<table id='gym-table' class='table table-".$table_style." ".($table_striped ? 'table-striped' : null)."' border='1'>";
    echo "<thead class='thead-".$table_header_style."'>";
    echo "<tr class='text-nowrap'>";
      echo "<th>Remove</th>";
      echo "<th>Team</th>";
      echo "<th>Available Slots</th>";
      echo "<th>Guarding Pokemon</th>";
      echo "<th>In Battle</th>";
      echo "<th>City</th>";
      echo "<th>Gym</th>";
      echo "<th>Updated</th>";
    echo "</tr>";
    echo "</thead>";
    while ($row = $result->fetch()) {	
      $geofence = $geofence_srvc->get_geofence($row['lat'], $row['lon']);
      $city = ($geofence == null ? $unknown_value : $geofence->name);
      $map_link = sprintf($googleMapsLink, $row["lat"], $row["lon"]);

	  $team = get_team($row['team_id']);
	  $available_slots = $row['availble_slots'];
	  $guarding_pokemon_id = $row['guarding_pokemon_id'];
	  $in_battle = $row['in_battle'];

      echo "<tr class='text-nowrap'>";
        echo "<td scope='row' class='text-center'><a title='Remove' data-toggle='tooltip' class='delete'><i class='fa fa-times'></i></a></td>";
        echo "<td><img src='./static/images/teams/" . strtolower($team) . ".png' height=32 width=32 />&nbsp;" . $team . "</td>";
        echo "<td>" . ($available_slots == 0 ? "Full" : $available_slots) . "</td>";
        echo "<td>" . $pokedex[$guarding_pokemon_id] . "</td>";
        echo "<td>" . ($in_battle ? "Under Attack!" : "Safe") . "</td>";
        echo "<td>" . $city . "</td>";
        echo "<td><a href='" . $map_link . "' target='_blank'>" . $row['name'] . "</a></td>";
        echo "<td>" . date($date_time_format, $row['updated']) . "</td>";
      echo "</tr>";
    }
    echo "</table>";
    echo "</div>";
		
  // Free result set
  unset($result);
  } else{
    echo "<p>No gyms found.</p>";
  }
} catch(PDOException $e){
  die("ERROR: Could not able to execute $sql. " . $e->getMessage());
}
// Close connection
unset($pdo);

?>

<script type="text/javascript">
$(document).on("click", ".delete", function(){
  $(this).parents("tr").remove();
  $(".add-new").removeAttr("disabled");
});
</script>