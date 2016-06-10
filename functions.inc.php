<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed');}

function directorypro_configpageload() {
	global $currentcomponent, $display;

	if ($display == 'directory' && (isset($_REQUEST['action']) && $_REQUEST['action']=='add'|| isset($_REQUEST['id']) && $_REQUEST['id'] != '')) {
		//set values or defualt them
		$deet = array('speech_enabled', 'pro_announcement', 'pro_repeat_loops',
							'pro_repeat_recording', 'pro_invalid_recording',
							'pro_invalid_destination', 'pro_say_extension', 'id', 'pro_retivr');
		if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'add') {
			foreach ($deet as $d) {
				switch ($d){
					case 'pro_repeat_loops';
						$dir[$d] = 2;
						break;
					case 'speech_enabled':
						$dir[$d] = true;
						break;
					case 'pro_announcement':
					case 'pro_repeat_recording':
					case 'pro_invalid_recording':
						$dir[$d] = 0;
						break;
					default:
		      	$dir[$d] = '';
						break;
				}
			}
		} else {
			$dir = directorypro_get_dir_details($_REQUEST['id']);
			if (!$dir) {
				foreach ($deet as $d) {
					switch ($d){
						case 'pro_repeat_loops';
							$dir[$d] = 2;
							break;
						case 'speech_enabled':
							$dir[$d] = true;
							break;
						case 'pro_invalid_destination':
							$dir[$d] = 'directory,' . $_REQUEST['id'] . ',1';
							break;
						case 'pro_announcement':
						case 'pro_repeat_recording':
						case 'pro_invalid_recording':
							$dir[$d] = 0;
							break;
						default:
			      	$dir[$d] = '';
							break;
					}
				}
			}

		}
	$section = _('Directory Pro Options (SPEECH)');
	//generate page
	//generate recording dropdown

	$currentcomponent->addoptlistitem('dirpro_recordings', 0, _('Default'));
	foreach(recordings_list() as $r){
		$currentcomponent->addoptlistitem('dirpro_recordings', $r['id'], $r['displayname']);
	}

	//build repeat_loops select list and defualt it to 3
	for($i = 1; $i < 11; $i++){
		$currentcomponent->addoptlistitem('repeat_loops', $i, $i);
	}
        $currentcomponent->setoptlistopts('dirpro_recordings', 'sort', false);

	$currentcomponent->addguielem($section, new gui_checkbox('speech_enabled', $dir['speech_enabled'],
									_('Speech Enabled'), _('Allow this directory to use speech recognition. PLease note: using TTS as a Name Announcement is not currently available in the Speech Directory, and will default to spelling the entry.'),true));
	$currentcomponent->addguielem($section, new gui_selectbox('pro_announcement', $currentcomponent->getoptlist('dirpro_recordings'),
									$dir['pro_announcement'], _('Announcement'), _('Greeting to be played on entry to the  speech directory'), false));
	$currentcomponent->addguielem($section, new gui_selectbox('pro_repeat_loops',
									$currentcomponent->getoptlist('repeat_loops'), $dir['pro_repeat_loops'], _('Invalid Retries'),
									_('Number of times to retry when receiving an invalid/unmatched response from the caller'), false));
	$currentcomponent->addguielem($section, new gui_selectbox('pro_repeat_recording',
									$currentcomponent->getoptlist('dirpro_recordings'), $dir['pro_repeat_recording'], _('Invalid Retry Recording'),
									_('Prompt to be played when an invalid/unmatched response is received, before prompting the caller to try again'), false));
	$currentcomponent->addguielem($section, new gui_selectbox('pro_invalid_recording',
									$currentcomponent->getoptlist('dirpro_recordings'), $dir['pro_invalid_recording'], _('Invalid Recording'),
									_('Prompt to be played before sending the caller to an alternate destination due to receiving the maximum amount of invalid/unmatched responses (as determined by Invalid Retries)'), false));
	$currentcomponent->addguielem($section, new gui_drawselects('pro_invalid_destination', 1,
									$dir['pro_invalid_destination'], _('Invalid Destination'),
									_('Destination to send the call to after Invalid Recording is played. You should consider setting the DMTF version of this directory to use in case the speech version doesn\'t work.'), false));
/*	$currentcomponent->addguielem($section, new gui_checkbox('pro_retivr', $dir['pro_retivr'], _('Return to IVR'),
									_('When selected, if the call passed through an IVR that had "Return to IVR" selected, the call will be returned there instead of the Invalid destination.'),true));
*/	$currentcomponent->addguielem($section, new gui_checkbox('pro_say_extension',
									$dir['pro_say_extension'], _('Announce Extension'),
									_('When checked, the name tag of the matched extension will be announced prior to the transfer'),true));

	//set defualt directory pro invalid destination to the dmtf
	$html = <<<EOD
	<script type="text/javascript">
		$(document).ready(function() {
			//if nothing is selected
	   		if (!$('[name="goto1"]').val()) {
				//check to see if there is already a directory item in the destinations list
				if ($('[name="goto1"] option[value="Directory"]').length < 1) {
					//if not, create it
					$('[name="goto1"]').append(
						$('<option></option>').val('Directory').html('Directory')
					)
				}
				//set the destination to Directory
				$('[name="goto1"]').val('Directory');
				//if we have a  directory id
				if ($('[name="id"]').val() != '') {
					//set the directorypro failover to that destination
					$('[name="Directory1"]').val('directory,'+ $('[name="id"]').val() + ',1');
				} else {
					//oterwise check if we have a directory1 sub-select
					if ($('[name=Directory]').length < 1) {
						$('[name="goto1"]').after('<select name="Directory1" style="display: inline-block; ">')
					}
					$('[name="Directory1"]').append(
						$('<option></option>').val('DTMF').html('This Directory (DTMF)')
						).val('DTMF');
				}
			}
		});
	</script>
EOD;
	$currentcomponent->addguielem($section, new guielement('rawhtml', $html, ''));
	}
}

function directorypro_configpageinit($pagename) {
	global $currentcomponent;
	if($pagename == 'directory'){
		$currentcomponent->addprocessfunc('directorypro_configprocess');
		$currentcomponent->addguifunc('directorypro_configpageload');
    return true;
	}

}

//prosses received arguments
function directorypro_configprocess(){
	if ($_REQUEST['display'] == 'directory') {

		global $db, $amp_conf;
		//get variables for directory_details
		$requestvars = array('id', 'speech_enabled', 'pro_announcement', 'pro_repeat_loops', 'pro_repeat_recording',
							'pro_invalid_recording', 'pro_invalid_destination', 'pro_say_extension', 'pro_retivr');
		foreach($requestvars as $var){
			$vars[$var] = isset($_REQUEST[$var]) ? $_REQUEST[$var] : '';
		}
		//these need to stay out of the array, otherwise they get passed in to the wrong places
		$entries	= isset($_REQUEST['entries']) ? $_REQUEST['entries'] : '';
		$action 	= isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

		switch($action){

			case 'edit':
				//get real dest
				$vars['pro_invalid_destination'] = $_REQUEST[$_REQUEST[$_REQUEST['pro_invalid_destination']]
													. str_replace('goto', '', $_REQUEST['pro_invalid_destination'])];
				if (!$vars['id']) {
					$sql = 'SELECT' . ( ($amp_conf["AMPDBENGINE"]=="sqlite3") ? ' last_insert_rowid()' : ' LAST_INSERT_ID()' );
					$vars['id'] = $db->getOne($sql);
					if ($db->IsError($vars['id'])){
						die_freepbx($vars['id']->getDebugInfo());
					}
				}
				//set a valid failover destination if one wasnt selected yet, defaulting
				//to this directory's dmtf options
				if ($vars['pro_invalid_destination'] == 'DTMF') {
					$vars['pro_invalid_destination'] = 'directory,' . $vars['id'] . ',1';
				}
				directorypro_save_dir_details($vars);
				directorypro_save_dir_entries($vars['id'], $entries);
			break;
			case 'delete':
				directorypro_delete($vars['id']);
			break;
		}
	}
}

function directorypro_get_config($engine) {
	global $ext, $db, $amp_conf;
	switch ($engine) {
		case 'asterisk':
			$sql = 'SELECT * FROM directorypro_details WHERE speech_enabled = "1" ORDER BY id';
			$results = sql($sql, 'getAll', DB_FETCHMODE_ASSOC);
			if ($results) {

					$c = 'directorypro';
					//$ext->add($c, 's', '', new ext_noop());
					$ext->add($c, 's', '', new ext_answer(''));
					$ext->add($c, 's', '', new ext_wait('1'));//greeting promt can sometimes get cut off if answered to fast
					$ext->add($c, 's', '', new ext_setvar('LOOPCOUNTER', '0'));

					//load grammar file and promt user for speech
					$ext->add($c, 's', 'start', new ext_noop('Starting directorypro :: {LOOPCOUNTER} = ${LOOPCOUNTER}'));
					$ext->add($c, 's', '', new ext_setvar('SPEECH_DTMF_TERMINATOR', '#'));
					$ext->add($c, 's', '', new ext_setvar('SPEECH_DTMF_MAXLEN', '3'));
					$ext->add($c, 's', '', new ext_setvar('LOOPCOUNTER', '$[${LOOPCOUNTER}+1]'));
					$ext->add($c, 's', '', new ext_gotoif('$["${LOOPCOUNTER}" > "${INVALID_RETRY}"]', 'exit,1'));
					$ext->add($c, 's', '', new ext_execif('$["${SPEECH(status)}" = "1"]', 'SpeechDestroy'));
					$ext->add($c, 's', '', new ext_speechcreate(''));
					$ext->add($c, 's', '', new ext_gosubif('$["${ERROR}" = "1"]', 'no-ports,1'));
					if (class_exists('ext_tryexec')) {
						$tryLoadGrammar = new ext_speechloadgrammar('directory', '/etc/asterisk/directorypro/directory-${GRAMFILE}.gram');
                                		$tryLoadGrammar = $tryLoadGrammar->output();
                                		$ext->add($c, 's', '', new ext_tryexec($tryLoadGrammar));
						$ext->add($c, 's', '', new ext_gotoif('$["${TRYSTATUS}" != "SUCCESS"]', 'no-ports,1'));
					} else {
						$ext->add($c, 's', '', new ext_speechloadgrammar('directory', '/etc/asterisk/directorypro/directory-${GRAMFILE}.gram'));
					}
					$ext->add($c, 's', '', new ext_speechactivategrammar('directory'));

					//only play the welcom message on the first loop
					$ext->add($c, 's', '', new ext_execif('$["${LOOPCOUNTER}" = "1"]', 'SpeechBackground', 'speech-chime&${WELCOM_REC},10'));
					$ext->add($c, 's', '', new ext_execif('$["${LOOPCOUNTER}" > "1"]', 'SpeechBackground', 'speech-chime&speech-dir-speech-nomatch,10'));

					$ext->add($c, 's', '', new ext_execif('$[${REGEX("^[0-9]+$" ${SPEECH_TEXT(0)})}]', 'set', 'keypress=${SPEECH_TEXT(0)}'));
					$ext->add($c, 's', '', new ext_gotoif('$[${REGEX("^[0-9]+$" ${SPEECH_TEXT(0)})}]', 'directory,${GRAMFILE},1'));//jump to dmtf directory if dmtf was received
					$ext->add($c, 's', '', new ext_gotoif('$[$[${SPEECH(spoke)} = 0] || $[${SPEECH(results)} = 0]]', 'start'));//restart if we didnt get anything back

					$ext->add($c, 's', '', new ext_noop('Number of speech results was: ${SPEECH(results)}'));
					$ext->add($c, 's', '', new ext_setvar('RES_LOOP', '$[${SPEECH(results)}-1]'));
					$ext->add($c, 's', '', new ext_setvar('SPEECH_RES_COUNT', '${SPEECH(results)}'));


					//get speech result and add them in to variables, looping through the results
					$ext->add($c, 's', 'save-res', new ext_setvar('SPEECH_RES_TEXT_${RES_LOOP}', '${SPEECH_TEXT(${RES_LOOP})}'));
					$ext->add($c, 's', '', new ext_setvar('SPEECH_RES_SCORE_${RES_LOOP}', '${SPEECH_SCORE(${RES_LOOP})}'));
					$ext->add($c, 's', '', new ext_setvar('RES_LOOP', '$[${RES_LOOP}-1]'));
					$ext->add($c, 's', '', new ext_gotoif('$[${RES_LOOP} > -1]', 'save-res'));
					$ext->add($c, 's', '', new ext_setvar('RES_LOOP', '$[${SPEECH_RES_COUNT}-1]'));
					$ext->add($c, 's', '', new ext_speechdeactivategrammar('directory'));
					$ext->add($c, 's', '', new ext_speechdestroy(''));

					//pick the first of our saved results and prosses it
					$ext->add($c, 's', 'res-loop', new ext_noop('Speech Recognition Score for ${RES_LOOP} was ${SPEECH_RES_SCORE_${RES_LOOP}}'));
					$ext->add($c, 's', '', new ext_noop('Speech Text for ${RES_LOOP} was ${SPEECH_RES_TEXT_${RES_LOOP}}'));
					//set variables for current speech entry
					$ext->add($c, 's', '', new ext_setvar('NAME', '${CUT(SPEECH_RES_TEXT_${RES_LOOP},_,1)}'));
					$ext->add($c, 's', '', new ext_setvar('EXTN', '${CUT(SPEECH_RES_TEXT_${RES_LOOP},_,2)}'));
					$ext->add($c, 's', '', new ext_setvar('AUDIO_TYPE', '${CUT(SPEECH_RES_TEXT_${RES_LOOP},_,3)}'));
					$ext->add($c, 's', '', new ext_setvar('DESTINATION', '${CUT(SPEECH_RES_TEXT_${RES_LOOP},_,4)}'));
					$ext->add($c, 's', '', new ext_setvar('RECORDING', '${CUT(SPEECH_RES_TEXT_${RES_LOOP},_,5)}'));
					$ext->add($c, 's', '', new ext_setvar('RES_LOOP', '$[${RES_LOOP}-1]'));
					$ext->add($c, 's', '', new ext_setvar('questionable_loop_${RES_LOOP}', ''));//need to clear this variable between speech loops
					//$ext->add($c, 's', '', new ext_gotoif('$[${SPEECH_RES_SCORE_$[${RES_LOOP}+1]} < 600]', 'res-loop'));
					//$ext->add($c, 's', '', new ext_noop('{SPEECH_RES_COUNT} = ${SPEECH_RES_COUNT}'));
					$ext->add($c, 's', '', new ext_gotoif('$[${SPEECH_RES_COUNT} > 1]', 'questionable', 'score-based'));
					$ext->add($c, 's', '', new ext_gotoif('$[${RES_LOOP} <= -1]', 'start'));//restart if weve exausted all our options

					//if we only have one entire, jump based on score.
					$ext->add($c, 's', 'score-based', new ext_gotoif('$[$[ "${SPEECH_RES_SCORE_0}" > "600"] && $[ "${SPEECH_RES_SCORE_0}" < "800"]]', 'questionable'));
					$ext->add($c, 's', '', new ext_gotoif('$[$[ "${SPEECH_RES_SCORE_0}" < "600"] && $[${RES_LOOP} > -1]]', 'res-loop'));
					$ext->add($c, 's', '', new ext_gotoif('$[$[ "${SPEECH_RES_SCORE_0}" < "600"] && $[${RES_LOOP} = -1]]', 'start'));

					//800 >;
					$ext->add($c, 's', 'GO', new ext_execif('$["${SPEECH(status)}" = "1"]', 'SpeechDestroy'));
					$ext->add($c, 's', '', new ext_execif('$["${ANNOUNCE_EXTEN}" = "1"]', 'Playback', 'pbx-transfer&to-extension'));
					$ext->add($c, 's', '', new ext_setvar('PLAYBACK_MODE', 'P'));//P=playback, no listen
					$ext->add($c, 's', '', new ext_execif('$["${ANNOUNCE_EXTEN}" = "1"]', 'Macro', 'directorypro-play-tag'));
					$ext->add($c, 's', '', new ext_goto('${CUT(DESTINATION,^,3)}', '${CUT(DESTINATION,^,2)}', '${CUT(DESTINATION,^,1)}'));

					//600 > && < 800
					$ext->add($c, 's', 'questionable', new ext_noop('questionable_loop_${RES_LOOP} = ${questionable_loop_${RES_LOOP}}'));
					$ext->add($c, 's', '', new ext_execif('$["${SPEECH(status)}" = "1"]', 'SpeechDestroy'));
					$ext->add($c, 's', '', new ext_speechcreate(''));
					$ext->add($c, 's', '', new ext_gosubif('$["${ERROR}" = "1"]', 'no-ports,1'));
					$ext->add($c, 's', '', new ext_setvar('SPEECH_DTMF_MAXLEN', '1'));
					//$ext->add($c, 's', '', new ext_setvar('questionable_loop_${RES_LOOP}', '${IF(${ISNULL(${questionable_loop_${RES_LOOP}})}?1:$[${questionable_loop_${RES_LOOP}}+1])}'));
					$ext->add($c, 's', '', new ext_execif('${ISNULL(${questionable_loop_${RES_LOOP}})}',
									'Set', 'questionable_loop_${RES_LOOP}=1',
									'Noop', 'Setting questionable_loop_${RES_LOOP} to ${INC(questionable_loop_${RES_LOOP})}'));
					$ext->add($c, 's', '', new ext_speechloadgrammar('yesno', '/etc/asterisk/directorypro/yesno.gram'));
					$ext->add($c, 's', '', new ext_speechactivategrammar('yesno'));
					$ext->add($c, 's', '', new ext_execif('$["${questionable_loop_${RES_LOOP}}" > "1"]', 'SpeechBackground', 'sorry-couldnt-understand,1'));
					$ext->add($c, 's', '', new ext_gotoif('$[${SPEECH(spoke)} = 1]', 'q-pros'));
					$ext->add($c, 's', '', new ext_execif('$["${questionable_loop_${RES_LOOP}}" = "${INVALID_RETRY}"]', 'SpeechBackground', 'speech-dir-instr,1'));
					$ext->add($c, 's', '', new ext_speechbackground('did-you-say', '1'));
					$ext->add($c, 's', '', new ext_gotoif('$[${SPEECH(spoke)} = 1]', 'q-pros'));
					$ext->add($c, 's', '', new ext_macro('directorypro-play-tag'));//used to confirm
					$ext->add($c, 's', 'q-pros', new ext_speechdeactivategrammar('yesno'));
					$ext->add($c, 's', '', new ext_noop('{SPEECH_TEXT(0)} = ${SPEECH_TEXT(0)}, {RES_LOOP} = ${RES_LOOP}, {SPEECH(spoke)} = ${SPEECH(spoke)}'));
					$ext->add($c, 's', '', new ext_gotoif('$[$["${SPEECH(spoke)}" = "0"] && $["${questionable_loop_${RES_LOOP}}" < "${INVALID_RETRY}"] && !$[${REGEX("^[0-9]+$" ${SPEECH_TEXT(0)})}]]', 'questionable'));
					$ext->add($c, 's', '', new ext_gotoif('$[$["${SPEECH_TEXT(0)}" = "yes"] || $["${SPEECH_TEXT(0)}" = "1"]]', 'GO'));
					$ext->add($c, 's', '', new ext_gotoif('$[ $[$["${SPEECH_TEXT(0)}" = "no"] || $["${SPEECH_TEXT(0)}" = "2"]] && $[${RES_LOOP} > -1] ]', 'res-loop', 'start'));

					//loop here when there are no speech ports available
					$ext->add($c, 'no-ports', '', new ext_playback('one-moment-please'));
					$ext->add($c, 'no-ports', '', new ext_wait('3'));
					$ext->add($c, 'no-ports', '', new ext_setvar('NO_PORTS', '${IF( $["${NO_PORTS}" = ""] ? 1 : $[${NO_PORTS} + 1] )}'));
					$ext->add($c, 'no-ports', '', new ext_execif('$["${NO_PORTS}" > "1"]', 'goto,exit,exit'));
					$ext->add($c, 'no-ports', '', new ext_return());

					//exit from here
					$ext->add($c, 'exit', '', new ext_noop('Oops, too many failures! Exiting now'));
					$ext->add($c, 'exit', '', new ext_playback('sorry-youre-having-problems'));
					$ext->add($c, 'exit', 'exit', new ext_goto('${CUT(INVALID_DEST,^,3)}', '${CUT(INVALID_DEST,^,2)}', '${CUT(INVALID_DEST,^,1)}'));

					//add macro that plays back the name tag
					$c = 'macro-directorypro-play-tag';
					$if_speech_end = new ext_gotoif('$["${SPEECH(spoke)}" = "1"]', 'end,1');
					$ext->add($c, 's', '', new ext_noop('Playing tag of type ${AUDIO_TYPE} for ${EXTN}'));
					$ext->add($c, 's', '', new ext_execif('$[${ISNULL(${AUDIO_TYPE})}]', 'hangup'));
					$ext->add($c, 's', '', new ext_setvar('PLAY_APP', '${IF($["${PLAYBACK_MODE}" = "P"]?Playback:SpeechBackground)}'));
					$ext->add($c, 's', '', new ext_goto('1','${AUDIO_TYPE}'));

					//if we have the vm greeting, play it - otherwise just spell
					$ext->add($c, 'vm', '', new ext_noop('hit vm'));
					$ext->add($c, 'vm', '', new ext_execif('$["$[$[!${STAT(f,${ASTSPOOLDIR}/voicemail/default/${EXTN}/greet.wav)}] || $[!${STAT(f,${ASTSPOOLDIR}/voicemail/default/${EXTN}/greet.WAV)}]]" = "0"]', 'Goto', 'spell,1'));
					$ext->add($c, 'vm', '', new ext_execif('$["1" = "1"]', '${PLAY_APP}', '${ASTSPOOLDIR}/voicemail/default/${EXTN}/greet,1'));
					$ext->add($c, 'vm', '', $if_speech_end);
					$ext->add($c, 'vm', '', new ext_execif('$["${PLAYBACK_MODE}" != "P"]', '${PLAY_APP}', 'silence/1,3'));
					$ext->add($c, 'vm', '', new ext_macroexit());

					//spell out the mathces name, by iterating over the name string
					$ext->add($c, 'spell', '', new ext_noop('hit spell'));
					$ext->add($c, 'spell', '', new ext_setvar('NAME_LEN', '${LEN(${NAME})}'));
					$ext->add($c, 'spell', '', new ext_setvar('CUR_POS', '-1'));
					$ext->add($c, 'spell', 'loop_top', new ext_setvar('CUR_POS', '$[${CUR_POS} + 1]'));
					$ext->add($c, 'spell', '', new ext_noop('READING: ${NAME:${CUR_POS}:1}'));
					$ext->add($c, 'spell', '', new ext_execif('$[${REGEX("[a-zA-Z]" ${NAME:${CUR_POS}:1})}]', '${PLAY_APP}', 'letters/${NAME:${CUR_POS}:1},1'));
					$ext->add($c, 'spell', '', $if_speech_end);
					$ext->add($c, 'spell', '', new ext_execif('$[${REGEX("[0-9]" ${NAME:${CUR_POS}:1})}]', '${PLAY_APP}', 'digits/${NAME:${CUR_POS}:1},1'));
					$ext->add($c, 'spell', '', $if_speech_end);
					$ext->add($c, 'spell', '', new ext_execif('$[${REGEX(" " ${NAME:${CUR_POS}:1})}]', '${PLAY_APP}', 'silence/1,1'));
					$ext->add($c, 'spell', '', $if_speech_end);
					$ext->add($c, 'spell', '', new ext_execif('$[${CUR_POS} != $[${NAME_LEN} - 1]]', 'Goto', 'loop_top'));//loop if htere are more charachters
					$ext->add($c, 'spell', '', new ext_goto('end,1'));

					//dont create a temp file for playback mode
					$ext->add($c, 'tts', '', new ext_noop('hit tts'));
					$ext->add($c, 'tts', '', new ext_execif('$["${PLAYBACK_MODE}" = "P"]', 'flite','${NAME}'));
					$ext->add($c, 'tts', '', new ext_execif('$["${PLAYBACK_MODE}" = "P"]', 'macroexit'));
					$ext->add($c, 'tts', '', new ext_set('TMP_FLITE', '${ASTSPOOLDIR}/tmp/dirpro-tts-${EPOCH}${RAND(100,999)}'));
					$ext->add($c, 'tts', '', new ext_system('flite -t "${NAME}" -o ${TMP_FLITE}.wav'));
					$ext->add($c, 'tts', '', new ext_speechbackground('${TMP_FLITE}', '10'));
					$ext->add($c, 'tts', '', new ext_system('rm ${TMP_FLITE}.wav &'));
					$ext->add($c, 'tts', '', new ext_execif('$[${ISNULL(${SPEECH(spoke)})}]', 'SpeechBackground', 'silence/1,3'));
					$ext->add($c, 'tts', '', new ext_macroexit());

					//playback a system recording
					$ext->add($c, 'sysrec', '', new ext_noop('hit sysrec'));
					$ext->add($c, 'sysrec', '', new ext_speechbackground('${RECORDING}', '5'));
					$ext->add($c, 'sysrec', '', new ext_goto('end,1'));

					//end here
					$ext->add($c, 'end', '', new ext_execif('$[${SPEECH(spoke)} = 0]', 'SpeechBackground', 'silence/1,3'));
					$ext->add($c, 'end', '', new ext_macroexit());

					//this is the actually context where directory pro starts, setting options for the rest of the session
	        		$c = 'ext-directorypro';
					foreach ($results as $r) {
						$r['pro_announcement']		= $r['pro_announcement']
													? recordings_get_file($r['pro_announcement'])
													: 'speech-dir-intro';
						$r['pro_repeat_recording']	= $r['pro_repeat_recording']
													? recordings_get_file($r['pro_repeat_recording'])
													: 'speech-dir-speech-nomatch';
						$r['pro_invalid_recording']	= $r['pro_invalid_recording']
													? recordings_get_file($r['pro_invalid_recording'])
													: 'speech-dir-speech-nomatch';
						$ext->add($c, $r['id'], '', new ext_noop('directory speech ${EXTEN}'));
						$ext->add($c, $r['id'], '', new ext_setvar('GRAMFILE', '${EXTEN}'));
						$ext->add($c, $r['id'], '', new ext_setvar('WELCOM_REC', $r['pro_announcement']));
						$ext->add($c, $r['id'], '', new ext_setvar('INVALID_RETRY', $r['pro_repeat_loops']));
						$ext->add($c, $r['id'], '', new ext_setvar('INVALID_RETRY_REC', $r['pro_repeat_recording']));
						$ext->add($c, $r['id'], '', new ext_setvar('INVALID_REC', $r['pro_invalid_recording']));
						$ext->add($c, $r['id'], '', new ext_setvar('INVALID_DEST', str_replace(',', '^', $r['pro_invalid_destination'])));
						$ext->add($c, $r['id'], '', new ext_setvar('ANNOUNCE_EXTEN', $r['pro_say_extension']));
	          $ext->add($c, $r['id'], '', new ext_goto('1','s','directorypro'));
					}

					//write out gramamrs
					directorypro_write_grammars();
			}
			break;
	}

}

function directorypro_list() {
	$sql		= 'SELECT id,dirname FROM directory_details ORDER BY dirname';
	$results	= sql($sql,'getAll',DB_FETCHMODE_ASSOC);
	return $results;
}

//jucie, juuuuuucie details..
function directorypro_get_dir_details($id) {
	global $db;
	$id		= $db->escapeSimple($id);
	$sql	= "SELECT * FROM directorypro_details WHERE ID = $id";
	$row	= sql($sql,'getRow',DB_FETCHMODE_ASSOC);
	return $row;
}

function directorypro_delete($id){
	global $db, $amp_conf;
	$id = $db->escapeSimple($id);
	sql("DELETE FROM directorypro_details WHERE id = $id");
	sql("DELETE FROM directorypro_entries WHERE id = $id");

	//delete grammar file if it exists
	$file = $amp_conf['ASTETCDIR'] . '/directorypro/directory-' . $id . '.gram';
	if (file_exists($file)) {
		unlink($file);
	}
}

function directorypro_destinations(){
	global $db;
	//ensure speech is enabled before returning it as a destination
	$sql		= 'select directory_details.id, directory_details.dirname FROM directory_details
					LEFT JOIN directorypro_details on directory_details.id = directorypro_details.id
						WHERE directorypro_details.speech_enabled = 1
					ORDER BY dirname';
	$results	= sql($sql,'getAll',DB_FETCHMODE_ASSOC);

	foreach($results as $row){
		$row['dirname']	= ($row['dirname'])?$row['dirname']:'Directory '.$row['id'] ;
		$extens[] 		= array('destination' => 'ext-directorypro,' . $row['id'] . ',1',
								'description' => $row['dirname'] . ' (speech)',
								'category' => _('Directory'));
	}
	return isset($extens) ? $extens : null;
}


// TODO: clean this up passing in $vals with expected positions for insert is very error prone! -PL
function directorypro_save_dir_details($vals){
	global $db, $amp_conf;
	//dbug('directorypro_save_dir_details called with ', $vals);
	foreach($vals as $key => $value) {
		$vals[$key] = $db->escapeSimple($value);
	}

	$sql	= 'REPLACE INTO directorypro_details (id, speech_enabled, pro_announcement,
				pro_repeat_loops, pro_repeat_recording,
				pro_invalid_recording, pro_invalid_destination, pro_say_extension,
				pro_retivr)
				VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';
	$foo	= $db->query($sql,$vals);
	if(DB::IsError($foo)) {
		die_freepbx(print_r($vals,true).' '.$foo->getDebugInfo());
	}

	return $vals['id'];
}

function directorypro_save_dir_entries($id, $entries){
	global $db;
	$id 	= $db->escapeSimple($id);
	sql("DELETE FROM directorypro_entries WHERE id = $id");

	if ($entries) {
		foreach($entries as $my => $e){
			if ($my != '' && $e['grammar']) {
				$sql = 'INSERT INTO directorypro_entries (id, e_id, grammar) VALUES (?, ? , ?)';
				$foo = $db->query($sql, array($id, $my, $e['grammar']));
				 if(DB::IsError($foo)) {
					die_freepbx(print_r($vals,true).' '.$foo->getDebugInfo());
				}
			}
		}
	}
}

function directorypro_get_dir_entries($id, $e_id){
	global $db;

	$sql = 'SELECT * FROM directorypro_entries WHERE id = ? AND e_id = ?';
	$res = $db->getAll($sql, array($id, $e_id), DB_FETCHMODE_ASSOC);
	return $res ? $res[0] : '';
}

//draw actuall html that will apear in directory table
function directorypro_draw_entries_tr_directory($opts) {
	$ret = false;
	$tr		= directorypro_get_dir_entries($opts['id'], $opts['e_id']);
	$ret 	= '<textarea  name="entries['
			. $opts['e_id']
			. '][grammar]" placeholder="speech grammars"/>'
			. (isset($tr['grammar']) ? $tr['grammar'] : '')
			. '</textarea>';

	return  array($ret);
}

function directorypro_write_grammars() {
	global $amp_conf;
	//write out grammars. Perhaps we should use a seperate function for this???
	$gram = '';
	//query should fill in the blanks where using the defualt names/values from the extension
	$sql = 'SELECT directorypro_entries.id, directorypro_entries.e_id, directorypro_entries.grammar,
			IF(directory_entries.type = "user", users.name, directory_entries.name) AS name, directory_entries.audio,
			IF(directory_entries.type = "user", users.extension, directory_entries.dial) AS dial, directory_entries.foreign_id
			FROM directorypro_entries
			LEFT JOIN directorypro_details ON directorypro_entries.id = directorypro_details.id AND directorypro_details.speech_enabled = "1"
			LEFT JOIN directory_entries ON directory_entries.id = directorypro_entries.id AND directory_entries.e_id = directorypro_entries.e_id
			LEFT JOIN users ON users.extension = directory_entries.foreign_id
			GROUP BY directorypro_entries.id, directorypro_entries.e_id';
	$results = sql($sql, 'getAll', DB_FETCHMODE_ASSOC);

	if (isset($results) && $results) {
		foreach ($results as $r) {
			//replace multipal entires with pipes, which = OR in grammars
			$srch = array("\r\n", "\n", "\r");
			$r['grammar'] = str_replace($srch, ' | ', trim($r['grammar']));
			 //we MUST use lowercase, otherwise we wont be able to spell out the persons name, as the sound files wont be found.
			//Also, no _ as we use those as field delimiters
			$r['name'] = str_replace('_', '', strtolower($r['name']));
			$exten = str_replace('_', '', strtolower($r['foreign_id']));
			$exten = $exten ? $exten : 0; //ensure were arent leaving this balnk, as it may not always be set
			//yup - from-internal ONLY at this point, as thats all the directory supports
			$dest =  'from-internal^' . str_replace('_', '', strtolower($r['dial'])) . '^1';
			if (isset($r['audio']) && is_numeric($r['audio']) && function_exists('recordings_get')) {
				$rex = recordings_get($r['audio']);
				$r['audio'] = 'sysrec';
				$record = $rex['filename'];
			} else {
				$record = '';
			}
			$gram[$r['id']][] = '(' . $r['grammar'] . ') {out="'
								. $r['name']
								. '_'
								. $exten
								. '_'
								. $r['audio']
								. '_'
								. $dest
								. ($record ? '_' . $record : '')
								. '";}';
		}
	}

	if ($gram) {
		foreach ($gram as $dir => $g) {
			$write = '';
			$write .= '#ABNF 1.0;' . "\n";
			$write .= 'mode voice;' . "\n";
			$write .= 'language en-US;' . "\n";
			$write .= 'tag-format <semantics/1.0.2006>;' . "\n\n";
			$write .= 'root $directory;' . "\n\n";
			$write .= '$directory = (' . "\n\n";
			$write .= "\t  " . trim(implode("\n\t| ", $g), "\n\t| ") . "\n";
			$write .= ');' . "\n";
			//dbug($amp_conf['ASTETCDIR'] . '/grammars/directory-' . $dir . '.gram', $write);
			file_put_contents($amp_conf['ASTETCDIR'] . '/directorypro/directory-' . $dir . '.gram', $write);
		}
	}

}
