<?php
session_start();
require_once($_SERVER['DOCUMENT_ROOT'] . '/inc/captcha/captcha.inc.php');
if (isset($_POST['ato_logout'])) {
    session_destroy();
    header('Location: //' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI']);
    exit();
}
if (isset($_POST['login'])) {
    if (file_exists($_POST['captcha-image'])) unlink($_POST['captcha-image']);
    if (!$_POST['booking-pw']) {
        $error = 'Please fill all the fields.';
    } else {
        $sentCode = htmlspecialchars($_POST["code"]);
        $result = (int)$_POST["result"];
        if (getExpressionResult($sentCode) !== $result) {
            $error = "Wrong Captcha.";
        } else {
            $bookingpw = hash("sha512", htmlspecialchars($_POST['booking-pw']) . $PARTID_MD5);
            $db_content = json_decode(coms_client_api(array("cmd" => "read", "paramJS" => array("table" => "v_csvexport_trainingorg",
                "where" => "a.coms_training_organisation_passwd_hash = '$bookingpw' && a.coms_training_organisation_id = $PARTID"))), true);
            if ($db_content) {
                $_SESSION['name'] = $db_content[0]['coms_training_organisation_name'];
                $_SESSION['coms_user_id'] = $db_content[0]['coms_training_organisation_id'];
                $_SESSION['user_type'] = $USER_TYPE;
            } else {
                $error = 'Wrong password';
            }
        }
    }
}
if (isset($_POST['create_event'])) {
    if (!isset($_POST['select_exam']) || !isset($_POST['select_trainer']) ||  !isset($_POST['select_proctor']) || !$_POST['date'] ||  !$_POST['time'] ||  !$_POST['location']) {
        $error = 'Please fill all the fields.';
    }
    if (!isset($error)) {
        $date = implode("-", array_reverse(explode(".", htmlspecialchars($_POST['date']))));
        $errr = json_decode(coms_client_api(array("cmd" => "create", "paramJS" => array("table" => "coms_exam_event", "row" => array(
            "coms_exam_id" => htmlspecialchars($_POST['select_exam']),
            "coms_trainer_id" => htmlspecialchars($_POST['select_trainer']),
            "coms_training_org_id" => htmlspecialchars($PARTID),
            "coms_proctor_id" => htmlspecialchars($_POST['select_proctor']),
            "coms_exam_event_start_date" => $date . " " . htmlspecialchars($_POST['time']),
            "coms_exam_event_location" => htmlspecialchars($_POST['location']),
            "coms_exam_event_info" => htmlspecialchars($_POST['exam_event_info']),
            "coms_delivery_type_id" => "5")))), true);
        if (!$errr[0]['allow_transition']) {
            $error = $errr[0]['message'];
        } elseif (isset($errr[0]['errormsg'])) {
            $error = $errr[0]['errormsg'];
        } else {
            $success = "The event has been successfully created";
        }
    }
}
if (isset($_POST['edit_event'])) {
    if (!isset($_POST['edit_trainer']) || !isset($_POST['edit_proctor']) || !$_POST['edit_location'] || !$_POST['time']) {
        $error = 'Please fill all the fields.';
    } else {
        $exam_event_id = htmlspecialchars($_POST['event_exam_id']);
        $edited_exam = json_decode(coms_client_api(array("cmd" => "read", "paramJS" => array("table" => "v_coms_trainingorg_exam_events", "where" => "a.coms_exam_event_id = $exam_event_id && coms_training_org_id = $_SESSION[coms_user_id]"))), true);
        if (!$edited_exam) {
            $error = 'You are trying to edit an incorrect Exam Event';
        } else {
            $date = htmlspecialchars($_POST['date']) . ' ' . htmlspecialchars($_POST['time']);
            $result = json_decode(coms_client_api(array(
                "cmd" => "makeTransition",
                "paramJS" => array("table" => 'coms_exam_event',
                    "row" => array(
                        "coms_exam_event_id" => $exam_event_id,
                        "coms_trainer_id" => htmlspecialchars($_POST['edit_trainer']),
                        "coms_proctor_id" => htmlspecialchars($_POST['edit_proctor']),
                        "coms_exam_event_location" => htmlspecialchars($_POST['edit_location']),
                        "coms_exam_event_info" => htmlspecialchars($_POST['exam_event_info']),
                        "coms_exam_event_start_date" => $date,
                        "state_id" => htmlspecialchars($_POST['state_id']),
                    )
                )
            )), true);
            if (!$result[0]['allow_transition']) {
                $error = $result[0]['message'];
            } elseif (isset($result[0]['errormsg'])) {
                $error = $result[0]['errormsg'];
            } else {
                $success = "The event has been successfully edited";
            }
        }
    }
}
if (isset($_POST['create_participant'])) {
    if (!$_POST['firstname'] || !$_POST['lastname'] || !$_POST['email']) {
        $error = 'Please fill all the fields.';
    } else {
        $exam_event_id = htmlspecialchars($_POST['exam_event_id']);
        $firstname = htmlspecialchars($_POST['firstname']);
        $lastname = htmlspecialchars($_POST['lastname']);
        $email = htmlspecialchars($_POST['email']);
        $language = isset($_POST['language']) ? htmlspecialchars($_POST['language']) : 6;
        $gender = isset($_POST['gender']) ? htmlspecialchars($_POST['gender']) : null;
        $date_of_birth = $_POST['date_of_birth'] ? htmlspecialchars($_POST['date_of_birth']) : null;
        $check_participant_email = json_decode(coms_client_api(array("cmd" => "read", "paramJS" => array("table" => "v_coms_participant__id__email", "where" => "coms_participant_emailadresss = '$email'"))), true);
        if ($check_participant_email) {
            $error = 'Participant with this email address already exists';
        }
        if (!isset($error)) {
            $place_of_birth = isset($_POST['place_of_birth']) ? htmlspecialchars($_POST['place_of_birth']) : null;
            $country_of_birth = isset($_POST['country_of_birth']) ? htmlspecialchars($_POST['country_of_birth']) : null;
            $errr = json_decode(coms_client_api(array("cmd" => "create", "paramJS" => array("table" => "coms_participant", "row" => array(
                "coms_participant_firstname" => $firstname,
                "coms_participant_lastname" => $lastname,
                "coms_participant_email" => $email,
                "coms_participant_language_id" => $language,
                "coms_participant_gender" => $gender,
                "coms_participant_dateofbirth" => $date_of_birth,
                "coms_participant_placeofbirth" => $place_of_birth,
                "coms_participant_birthcountry" => $country_of_birth
            )))), true);
            if (!$errr[0]['allow_transition']) {
                $error = $errr[0]['message'];
            } elseif (isset($errr[0]['errormsg'])) {
                $error = $errr[0]['errormsg'];
            } else {
                $created_participant = json_decode(coms_client_api(array("cmd" => "read", "paramJS" => array("table" => "v_coms_participant__id__email", "where" => "coms_participant_emailadresss = '$email'"))), true);
                if ($created_participant) {
                    $success = "The participant has been successfully created.";
                    $errr = json_decode(coms_client_api(array("cmd" => "create", "paramJS" => array("table" => "coms_participant_exam_event", "row" => array(
                        "coms_participant_id" => $created_participant[0]['coms_participant_id'],
                        "coms_exam_event_id" => $exam_event_id
                    )))), true);
                    if (!$errr[0]['allow_transition']) {
                        $error = $errr[0]['message'];
                    } elseif (isset($errr[0]['errormsg'])) {
                        $error = $errr[0]['errormsg'];
                    } else {
                        $success = "The participant has been successfully added to the event.";
                    }
                } else {
                    $error = "The Participant can't be created.";
                }
            }
        }
    }
}
if (isset($_POST['edit_participant'])) {
    if (!$_POST['firstname'] || !$_POST['lastname']) {
        $error = 'Please fill all the fields.';
    } else {
        $participant_id = htmlspecialchars($_POST['participant_id']);
        $firstname = htmlspecialchars($_POST['firstname']);
        $lastname = htmlspecialchars($_POST['lastname']);
        $email = htmlspecialchars($_POST['email']);
        $language = isset($_POST['language']) ? htmlspecialchars($_POST['language']) : 6;
        $gender = isset($_POST['gender']) ? htmlspecialchars($_POST['gender']) : null;
        $date_of_birth = $_POST['date_of_birth'] ? htmlspecialchars($_POST['date_of_birth']) : null;
        $place_of_birth = isset($_POST['place_of_birth']) ? htmlspecialchars($_POST['place_of_birth']) : null;
        $country_of_birth = isset($_POST['country_of_birth']) ? htmlspecialchars($_POST['country_of_birth']) : null;
        if ($email) {
            $check_participant_email = json_decode(coms_client_api(array("cmd" => "read", "paramJS" => array("table" => "v_coms_participant__id__email", "where" => "coms_participant_emailadresss = '$email'"))), true);
            if ($check_participant_email) {
                $error = 'Participant with this email address already exists';
            } else {
                $errr = json_decode(coms_client_api(array("cmd" => "makeTransition", "paramJS" => array("table" => "coms_participant", "row" => array(
                        "coms_participant_id" => $participant_id,
                        "coms_participant_firstname" => $firstname,
                        "coms_participant_lastname" => $lastname,
                        "coms_participant_email" => $email,
                        "coms_participant_language_id" => $language,
                        "coms_participant_gender" => $gender,
                        "coms_participant_dateofbirth" => $date_of_birth,
                        "coms_participant_placeofbirth" => $place_of_birth,
                        "coms_participant_birthcountry" => $country_of_birth,
                        "state_id" => 110
                )))), true);
            }
        } else {
            $errr = json_decode(coms_client_api(array("cmd" => "makeTransition", "paramJS" => array("table" => "coms_participant", "row" => array(
                "coms_participant_id" => $participant_id,
                "coms_participant_firstname" => $firstname,
                "coms_participant_lastname" => $lastname,
                "coms_participant_language_id" => $language,
                "coms_participant_gender" => $gender,
                "coms_participant_dateofbirth" => $date_of_birth,
                "coms_participant_placeofbirth" => $place_of_birth,
                "coms_participant_birthcountry" => $country_of_birth,
                "state_id" => 110
            )))), true);
        }
        if (!$errr[0]['allow_transition']) {
            $error = $errr[0]['message'];
        } elseif (isset($errr[0]['errormsg'])) {
            $error = $errr[0]['errormsg'];
        } else {
            $success = "The participant has been successfully edited";
        }
    }
}

if (isset($_POST['add_existing_particiant'])) {
    if (!isset($_POST['check_participant'])) {
        $error = 'Please select a participant';
    } else {
        $participants = $_POST['check_participant'];
        $exam_event_id = $_POST['exam_event_id'];
        foreach ($participants as $participant) {
            $participant_exist = json_decode(coms_client_api(array("cmd" => "read", "paramJS" => array("table" => "v_coms_participant__exam_event", "where" => "coms_training_org_id = $_SESSION[coms_user_id] && coms_exam_event_id = $exam_event_id && coms_participant_id = $participant"))), true);
            if ($participant_exist) {
                $error = 'This participant is already added to this exam event';
            } else {
                $errr = json_decode(coms_client_api(array("cmd" => "create", "paramJS" => array("table" => "coms_participant_exam_event", "row" => array(
                    "coms_participant_id" => $participant,
                    "coms_exam_event_id" => $exam_event_id
                )))), true);
                if (!$errr[0]['allow_transition']) {
                    $error = $errr[0]['message'];
                } elseif (isset($errr[0]['errormsg'])) {
                    $error = $errr[0]['errormsg'];
                } else {
                    $success = "The participants have been successfully added to the event.";
                }
            }
        }
    }
}

if (isset($_POST['add_anonymous_exams'])) {
    $exam_event_id = htmlspecialchars($_POST['exam_event_id']);
    $date = htmlspecialchars($_POST['anonymous_exams_date']);
    $additional_exam_info = htmlspecialchars($_POST['anonymous_exams']);
    $edited_exam = json_decode(coms_client_api(array("cmd" => "read", "paramJS" => array("table" => "v_exam_event__exam__trainingorg__trainer", "where" => "a.coms_exam_event_id = $exam_event_id && coms_training_org_id = $_SESSION[coms_user_id]"))), true);
    if (!$edited_exam) {
        $error = 'You are trying to edit an incorrect Exam Event';
    } else {
        if (isset($edited_exam[0]['coms_exam_event_info'])) {
            $exam_event_info = $date . ":\r\n" . $additional_exam_info . ":\r\n" . $edited_exam[0]['coms_exam_event_info'];
        } else {
            $exam_event_info = $date . ":\r\n" . $additional_exam_info;
        }
        $result = json_decode(coms_client_api(array(
            "cmd" => "makeTransition",
            "paramJS" => array("table" => 'coms_exam_event',
                "row" => array(
                    "coms_exam_event_id" => $exam_event_id,
                    "coms_exam_event_info" => $exam_event_info,
                    "state_id" => $edited_exam[0]['event_state_id']
                )
            )
        )), true);
        if (!$result[0]['allow_transition']) {
            $error = $result[0]['message'];
        } elseif (isset($result[0]['errormsg'])) {
            $error = $result[0]['errormsg'];
        } else {
            $success = "Additional exam info added.";
        }
    }
}

/**
 * @param $exam
 * @return bool
 */
function pastExams($exam) {
    return($exam['coms_exam_event_start_date'] < date('Y-m-d H:i:s'));
}

/**
 * @param $exam
 * @return bool
 */
function futureExams($exam) {
    return($exam['coms_exam_event_start_date'] >= date('Y-m-d H:i:s'));
}

/**
 * @param $booked_exams
 * @return string
 */
function examEventsOutput($booked_exams) {
    $past_exams = array_filter($booked_exams, 'pastExams');
    $future_exams = array_filter($booked_exams, 'futureExams');
    $output = "<div class='events-nav'><div class='add-new-exam-event'>
        <a href='#'><i class='fas fa-plus-circle fa-3x'></i></a>
        </div>
        <div class='events-buttons'>
        <a id='future' href='#'>Future (" . count($future_exams) . ")</a>
        <a id='past' href='#'>Past (" . count($past_exams) . ")</a>
        <a id='all' class='active' href='#'>All (" . count($booked_exams) . ")</a>
        </div></div>
        <table id='exam_event_table' class='coms-js-table main-table'><thead>
        <th class='no-sort'></th>
        <th class='no-sort'></th>
        <th>Event ID</th>
        <th>Event Name</th>
        <th class='sort-by-date'>Start Date</th>
        <th>Trainer Lastname</th>
        <th>Trainer Firstname</th>
        <th>Proctor Lastname</th>
        <th>Proctor Firstname</th>
        <th>Event State</th>
        <th class='no-sort'></th>
        </thead><tbody>";
        $first = true;
    foreach ($booked_exams as $booked_exam) {
        if ($booked_exam['coms_exam_event_start_date'] < date('Y-m-d H:i:s') && $first) {
            $past_event = true;
        } elseif (($booked_exam['coms_exam_event_start_date'] < date('Y-m-d H:i:s')) && !isset($past_event) && !$first) {
            $output .= "<tr class='row-border-top past-events'>";
            $past_event = true;
        } else {
            $output .= "<tr>";
        }
        if ($first) $first = false;
        $cancellable_states = array(
            33,
            34,
            147
        );
        $editable_states = array(
            33,
            34,
            35,
            36,
            37,
            147,
        );
        if (in_array($booked_exam['state_id'], $editable_states)) {
            $output .= "<td><a href='#' class='edit-exam-event' data-coms_exam_event_id='" . $booked_exam['coms_exam_event_id'] . "' data-coms_exam_event_state='" . $booked_exam['state'] . "'><i class='fas fa-edit'></i></a></td>";
        } else {
            $output .= "<td></td>";
        }
        $output .= "<td><a href='#' class='show-participation-list' data-coms_exam_event_id='" . $booked_exam['coms_exam_event_id'] . "'><i class='fas fa-th-list'></i></a></td>";
        $output .= "<td>" . $booked_exam['coms_exam_event_id'] . "</td>";
        $output .= "<td>" . $booked_exam['coms_exam_name'] . "</td>";
        $output .= "<td>" . $booked_exam['coms_exam_event_start_date'] . "</td>";
        $output .= "<td>" . $booked_exam['coms_trainer_lastname'] . "</td>";
        $output .= "<td>" . $booked_exam['coms_trainer_firstname'] . "</td>";
        $output .= "<td>" . $booked_exam['coms_proctor_lastname'] . "</td>";
        $output .= "<td>" . $booked_exam['coms_proctor_firstname'] . "</td>";
        $output .= "<td>" . $booked_exam['state'] . "</td>";
        if (in_array($booked_exam['state_id'], $cancellable_states)) {
            $event_name = $booked_exam['coms_exam_name'] . ' ' . $booked_exam['coms_exam_event_start_date'];
            $output .= "<td><a href='#' class='cancel-exam-event' data-coms_exam_event_name='" . $event_name . "' data-coms_exam_event_id='" . $booked_exam['coms_exam_event_id'] . "'><i class='far fa-times-circle'></i></a></td>";
        } else {
            $output .= "<td></td>";
        }
        $output .= "</tr>";
    }
    $output .= "</tbody></table>";
    return $output;
}

/**
 * @param $exams
 * @return string
 */
function examsOutput($exams) {
    $output = "<table id='exams_table' class='coms-js-table not-main-table'><thead>
        <th>Event ID</th>
        <th>Exam Name</th>
        <th>State</th>
        <th>Language</th>
        </thead><tbody>";
    foreach ($exams as $exam) {
        $output .= "<tr>";
        $output .= "<td>" . $exam['coms_exam_id'] . "</td>";
        $output .= "<td>" . $exam['coms_exam_name'] . "</td>";
        $output .= "<td>" . $exam['state'] . "</td>";
        $output .= "<td>" . $exam['language'] . "</td>";
        $output .= "</tr>";
    }
    $output .= "</tbody></table>";
    return $output;
}

/**
 * @param $trainers
 * @return string
 */
function trainerOutput($trainers) {
    $output = "<table id='trainer_table' class='coms-js-table not-main-table'><thead>
        <th>Trainer Id</th>
        <th>Trainer Firstname</th>
        <th>Trainer Lastname</th>
        <th>State</th>
        </thead><tbody>";
    foreach ($trainers as $trainer) {
        $output .= "<tr>";
        $output .= "<td>" . $trainer['coms_trainer_id'] . "</td>";
        $output .= "<td>" . $trainer['coms_trainer_firstname'] . "</td>";
        $output .= "<td>" . $trainer['coms_trainer_lastname'] . "</td>";
        $output .= "<td>" . $trainer['state'] . "</td>";
        $output .= "</tr>";
    }
    $output .= "</tbody></table>";
    return $output;
}

/**
 * @param $proctors
 * @return string
 */
function proctorOutput($proctors) {
    $output = "<table id='proctor_table' class='coms-js-table not-main-table'><thead>
        <th>Proctor Id</th>
        <th>Proctor Firstname</th>
        <th>Proctor Lastname</th>
        <th>State</th>
        </thead><tbody>";
    foreach ($proctors as $proctor) {
        $output .= "<tr>";
        $output .= "<td>" . $proctor['coms_proctor_id'] . "</td>";
        $output .= "<td>" . $proctor['coms_proctor_firstname'] . "</td>";
        $output .= "<td>" . $proctor['coms_proctor_lastname'] . "</td>";
        $output .= "<td>" . $proctor['state'] . "</td>";
        $output .= "</tr>";
    }
    $output .= "</tbody></table>";
    return $output;
}

/**
 * @param $a
 * @param $b
 * @return false|int
 */
function date_compare($a, $b)
{
    return strtotime($b["coms_exam_event_start_date"]) - strtotime($a["coms_exam_event_start_date"]);
}

/**
 * @param $array
 * @param $key
 * @return array
 */
function unique_multidim_array($array, $key) {
    $temp_array = array();
    $i = 0;
    $key_array = array();

    foreach($array as $val) {
        if (!in_array($val[$key], $key_array)) {
            $key_array[$i] = $val[$key];
            $temp_array[$i] = $val;
        }
        $i++;
    }
    return $temp_array;
}

if (!isset($_SESSION['user_id']) || $_SESSION['coms_user_id'] !== $PARTID  || $_SESSION['user_type'] != 'ato') {
    generateImage($expression->n1.' + '.$expression->n2.' =', $captchaImage);
    require_once($_SERVER['DOCUMENT_ROOT'] . '/inc/templates/COMS_Client_login.inc.php');
} else {
    $exams_json = coms_client_api(array("cmd" => "read", "paramJS" => array("table" => "v_csvexport_trainingorg_exam", "where" => "a.coms_training_organisation_id = $PARTID")));
    $exams = json_decode($exams_json, true);
    $trainers_json = coms_client_api(array("cmd" => "read", "paramJS" => array("table" => "v_csvexport_trainingorg_trainer", "where" => "a.coms_training_organisation_id = $PARTID")));
    $trainers = json_decode($trainers_json, true);
    $proctors_json = coms_client_api(array("cmd" => "read", "paramJS" => array("table" => "v_csvexport_trainingorg_proctor", "where" => "a.coms_training_organisation_id = $PARTID")));
    $proctors = json_decode($proctors_json, true);
    $booked_exams = json_decode(coms_client_api(array("cmd" => "read", "paramJS" => array("table" => "v_coms_trainingorg_exam_events", "where" => "a.coms_training_org_id = $PARTID"))), true);
    usort($booked_exams, 'date_compare');

    $trid = array();
    foreach ($trainers as $trainer) {
        $trid[] = $trainer['coms_trainer_id'];
    }
    $exid = array();
    foreach ($exams as $exam) {
        $exid[] = $exam['coms_exam_id'];
    }
    $tridcsv = implode(",", $trid);
    $exidcsv = implode(",", $exid);
    $trexor = coms_client_api(array("cmd" => "read", "paramJS" => array("table" => "v_csvexport_trainer_exam", "where" => "coms_trainer_id in ($tridcsv) && coms_exam_id in ($exidcsv)")));
    $all_participants = json_decode(coms_client_api(array("cmd" => "read", "paramJS" => array("table" => "v_coms_participant__exam_event", "where" => "coms_training_org_id = $_SESSION[coms_user_id]"))), true);
    $all_participants = unique_multidim_array($all_participants, 'coms_participant_id');
    $all_participants = json_encode($all_participants);
    require_once($_SERVER['DOCUMENT_ROOT'] . '/inc/templates/COMS_Client_main.inc.php');
}