This plugin allows MentorPRO users to upload and share documents as follows:

Mentees

Mentees can upload documents and share with specific mentor(s) or all mentors.
Mentees can see the documents that they have uploaded, plus documents shared specifically with them by a mentor, or documents that have been shared with ALL mentees by PMs or Mentors.

Mentors

Mentors can upload documents and share with specific mentee(s) or all mentees.
Mentors can see the documents that they have uploaded, plus documents shared specifically with them by a mentee.

PMs

PMs can upload documents and share with specific mentee(s) or all mentees.
PMs can see the documents that they have uploaded by any mentee or any mentor.

Sharing is handled using metadata as follow:

meta_key				meta_value	
document_type			suffix (allowed values: .jpg,.jpeg,.gif,.png,.doc,.docx,.pdf,.ppt,.ppts,.xls,.xlsx)	
assigned_client			client_id	
document_user_mentee	array of mentees specified by mentor or PM. (i.e. a:2:{i:0;i:1039;i:1;i:1036;}). 
						Only set if "Share with all mentees" is not checked.
document_user_mentor	array of mentors specified by mentee. (i.e. a:1:{i:0;i:1032;}). Only set if "Share with all mentors" is not checked.
document_roles			mentee or mentor, indicates share with ALL users with that role (i.e. (a:1:{i:0;s:6:"mentee";}, a:1:{i:0;s:6:"mentor";})
document_url			URL of shared document	


share_with_all_mentors is set by form 
share_with_all_mentees is set by form

