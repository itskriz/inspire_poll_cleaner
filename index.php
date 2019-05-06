<!DOCTYPE html>
<html>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<title>Inspire 305 Poll Cleaner Tool</title>
	<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
	<style type="text/css">
		.container {
			max-width: 720px;
		}
	</style>
</head>
<?php
	$banned_emails = array(
		'roarmedia.com',
		'unitedwaymiami.org',
		'unitedwaymiami.com'
	);
	$alertType = 'dark';
	$message = 'Note: Please upload a pipe-delimited ("|") .csv file.';
	if (!is_dir('tmp/')) {
		mkdir('tmp');
	}
	if (!is_dir('results/')) {
		mkdir('results');
	}
	if (isset($_POST['submit'])) {
		$fileTmpPath = $_FILES['upload']['tmp_name'];
		$fileName = $_FILES['upload']['name'];
		$fileType = $_FILES['upload']['type'];
		$fileNameCmps = explode('.', $fileName);
		$fileExtension = strtolower(end($fileNameCmps));

		$newFileName = md5(time() . $fileName) . '.' . $fileExtension;
		$uploadFileDir = './tmp/';

		$dest_path = $uploadFileDir . $newFileName;

		if (move_uploaded_file($fileTmpPath, $dest_path)) {
			$alertType = 'info';
			$message = 'File moved successfully. Reading document...';
			$filename = 'tmp/' . $newFileName;
			$file_array = []; 
			if (($h = fopen("{$filename}", "r")) !== FALSE) {
			  while (($data = fgetcsv($h, 1000, "|")) !== FALSE) {
			    $file_array[] = $data;		
			  }
			  fclose($h);
			}
			$csv_headers = array_shift($file_array);
			for ($i = 0; $i < count($csv_headers); $i++) {
				$csv_headers[$i] = strtolower(str_replace(array('"', ' (required)'), '', preg_replace('/[\x00-\x1F\x7F]/', '', $csv_headers[$i])));
			}
			if (isset($_POST['fromDate'])) {
				$fdate = strtotime($_POST['fromDate']);
			} else {
				$fdate = strtotime('2019-05-01');
			}
			if (isset($_POST['toDate'])) {
				$tdate = strtotime($_POST['toDate'] . '+ 1 days');
			} else {
				$tdate = strtotime('2019-05-16');
			}
			$cdate = null;
			$status = null;
			$time = null;
			$ip = null;
			$choices = null;
			$email = null;
			for ($i = 0; $i < count($csv_headers); $i++) {
				$value = $csv_headers[$i];
				if ($value == 'status') {
					$status = $i;
				}
				if ($value == 'time') {
					$time = $i;
				}
				if ($value == 'ip') {
					$ip = $i;
				}
				if ($value == 'choices') {
					$choices = $i;
				}
				if ($value == 'email address') {
					$email = $i;
				}
			}
			$votes = array();
			$server_rejects = array();
			$script_rejects = array();
			$accepted_votes = array();
			if (null !== $status && null !== $time && null !== $ip && null !== $choices && null !== $email) {
				for ($i = 0; $i < count($file_array); $i++) {
					$ip_dups = array();
					$email_dups = array();
					$row = $file_array[$i];
					for ($j = 0; $j < count($row); $j++) {
						$value = strtolower(str_replace('"', '', preg_replace('/[\x00-\x1F\x7F]/', '', $row[$j])));
						$row[$j] = $value;
					}
					if ('accepted' == $row[$status]) {
						$vote_date_raw = explode(' ', $row[$time]);
						$vote_date = strtotime($vote_date_raw[0]);
						//echo '<pre>';
						//var_dump($fdate);
						//var_dump($tdate);
						//var_dump($vote_date);
						//echo '</pre>';
						if ($vote_date >= $fdate && $vote_date <= $tdate) {
							if (null == $cdate || $vote_date > $cdate) {
								$cdate = $vote_date;
								$ip_dups = array();
								$email_dups = array();
							}
							if (!in_array($row[$ip], $ip_dups) || !in_array($row[$email], $email_dups)) {
								array_push($ip_dups, $row[$ip]);
								array_push($email_dups, $row[$email]);
								array_push($accepted_votes, $row);
								$email_check = explode('@', $row[$email]);
								$email_check = end($email_check);
								if (!in_array($email_check, $banned_emails)) {
									$vote = $row[$choices];
									if (!isset($votes[$vote]) && !empty($vote)) {
										$votes[$vote] = 1;
									} else {
										$votes[$vote]++;
									}
								} else {
									$row[$status] = 'banned';
									array_push($script_rejects, $row);
								}
							}
						} else {
							$row[$status] = 'rejected';
							array_push($script_rejects, $row);
						}
					} else {
						array_push($server_rejects, $row);
					}
				}
				$timestamp = md5(time());
				$gen_docs = array();
				$results_path = 'results/';

				$vote_results = array(
					array('Choices', 'Votes')
				);
				$total_valid_votes = 0;
				foreach ($votes as $key => $value) {
					$total_valid_votes += $value;
					array_push($vote_results, array(strtoupper($key), $value));
				}
				array_push($vote_results, array('Total Valid Votes', $total_valid_votes));

				$fp_docs = array(
					array(
						'filename'	=> 'inspire305-accepted-votes',
						'data'			=> $accepted_votes,
					),
					array(
						'filename'	=> 'inspire305-server-rejected',
						'data'			=> $server_rejects,
					),
					array(
						'filename'	=> 'inspire305-script-rejected',
						'data'			=> $script_rejects,
					),
				);
				foreach ($fp_docs as $fp_doc) {
					if (!empty($fp_doc['filename']) && is_array($fp_doc['data']) && !empty($fp_doc['data'])) {
						$file = $results_path . $fp_doc['filename'] . '_' . $timestamp . '.csv';
						$data = $fp_doc['data'];


						$fp = fopen($file, 'wb');
						$fp_headers = array();
						for ($i = 0; $i < count($csv_headers); $i++) {
							$header = ucwords($csv_headers[$i]);
							$fp_headers[$i] = $header;
						}
						fputcsv($fp, $fp_headers);
						for ($i = 0; $i < count($data); $i++) {
							for ($j = 0; $j < count($data[$i]); $j++) {
								$data[$i][$j] = ucwords($data[$i][$j]);
							}
							fputcsv($fp, $data[$i]);
						}
						fclose($fp);
						array_push($gen_docs, $file);
					}
				}
				$final_results_csv = $results_path . 'inspire305-voting-results_' . $timestamp . '.csv';
				$frp = fopen($final_results_csv, 'wb');
				foreach ($vote_results as $row) {
					fputcsv($frp, $row);
				}
				fclose($frp);
				array_push($gen_docs, $final_results_csv);

				if (!empty($gen_docs)) {
					$zip = new ZipArchive();
					$zip->open('results/inspire305-results_' . $timestamp . '.zip', ZipArchive::CREATE);
					foreach ($gen_docs as $file) {
						$alertType = 'info';
						$message = 'Adding ' . $file .' to zip archive...';
						$zip->addFile($file, $file);
					}
					$zip->close();
				} else {
					$alertType = 'danger';
					$message = 'ERROR: No files generated.';
				}
				unlink($dest_path);
				for ($i = 0; $i < count($gen_docs); $i++) {
					unlink($gen_docs[$i]);
				}
				$alertType = 'success';
				$message = 'Poll results successfully generated! If your download does not start automatically, <a id="download" href="'.'results/inspire305-results_' . $timestamp . '.zip" title="Download results zip." class="alert-link">click here to download</a>. (.zip)';
			} else {
				$alertType = 'danger';
				$message = 'ERROR: There was a problem locating the following columns in this document: ';
				$cols = array();
				if (null == $status) {
					array_push($cols, 'Status');
				}
				if (null == $time) {
					array_push($cols, 'Time');
				}
				if (null == $ip) {
					array_push($cols, 'IP');
				}
				if (null == $choices) {
					array_push($cols, 'Choices');
				}
				if (null == $email) {
					array_push($cols, 'Email Address');
				}
				$message = $message . implode(', ', $cols);
			}
		} else {
			$alertType = 'danger';
			$message = 'ERROR: There was an issue attempting to upload the file.';
		}
	}
?>
<body>
	<header class="container mt-4">
		<h1 class="display-4">
			Inspire 305 Poll Cleaner Tool
		</h1>
		<p class="alert alert-<?php echo $alertType; ?>">
			<?php echo $message; ?>
		</p>
	</header>
	<main class="container mt-4 mb-4">
		<?php
			if (is_array($votes) && !empty($votes)) {
				echo '<table class="mb-4 table thead-dark table-stripped table-bordered"><tbody>';
				echo '<tr><th class="table-dark" colspan="2" scope="row">Vote Tallies</th></tr>';
				$vote_total = 0;
				foreach ($votes as $key => $count) {
					echo '<tr>';
					echo '<th>'.strtoupper($key).'</th>';
					echo '<td>'.$count.'</td>';
					$vote_total += $count;
					echo '</tr>';
				}
				echo '<tr><th class="table-success">Total (Valid) Votes</th><th class="table-success">'.$vote_total.'</th></tr>';
				echo '</tbody></table>';
				echo '<hr class="mb-4">';
			}
		?>
		<?php
			if(isset($_POST['submit'])) {
				$header_mod = ' new';
			} else {
				$header_mod = '';
			}
			echo '<h2>Generate'.$header_mod.' poll results</h2>';
		?>
		<form action="/inspire_poll_cleaner/index.php" method="post" enctype="multipart/form-data">
			<div class="form-row">
				<fieldset class="col-md-12">
					<label for="upload">
						Upload CSV File
					</label>
					<input type="file" name="upload" class="form-control-file" accept="text/csv" required>
					<small class="form-text text-muted">Only .csv files accepted.</small>
				</fieldset>
			</div>
			<div class="form-row">
				<fieldset class="col-md-6">
					<label for="fromDate">
						Start date:
						<span class="text-danger">*</span>
						<input class="form-control" type="text" value="2019-05-01" placeholder="YYYY-MM-DD" name="fromDate" required>
					</label>
				</fieldset>
				<fieldset class="col-md-6">
					<label for="toDate">
						Through date:
						<span class="text-danger">*</span>
						<input class="form-control" type="text" value="2019-05-15" placeholder="YYYY-MM-DD" name="toDate" required>
					</label>
				</fieldset>
			</div>
			<div class="form-row">
				<fieldset class="col-md-12">
					<input class="btn btn-primary mt-4" type="submit" value="Submit" name="submit">
				</fieldset>
			</div>
		</form>
	</main>
	<footer class="container mt-4">
		<hr>
		<p class="text-muted small text-center">Developed by Kris Williams/Roar Media exclusively for use by Inspire305. All Rights Reserved.</p>
	</footer>
	<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script>
	<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>
	<script type="text/javascript">
		jQuery(document).ready(function($) {
			if ($('#download').length) {
				window.location.href = $('#download').attr('href');
			}
		});
	</script>
</body>
</html>