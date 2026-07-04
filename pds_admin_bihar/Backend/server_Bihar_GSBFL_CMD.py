#!/usr/bin/python
# -*- coding: utf-8 -*-

import sys
import math
import numpy as np
import json
import pickle
import os.path
from os import path
import shutil
import subprocess
import pymongo
import uuid
import pandas as pd
from pulp import *
import excelrd
from flask import Flask, request, jsonify
from flask_cors import CORS
import mysql.connector
import requests
import time
import secrets
import string
from datetime import datetime
from pulp import LpStatus, LpStatusInfeasible, LpStatusUnbounded, LpStatusNotSolved, LpStatusUndefined
import msoffcrypto # Install this in requirement using 'pip install msoffcrypto-tool' & 'pip install xlrd'
from io import BytesIO
from cryptography.fernet import Fernet	
import os
import io
from datetime import datetime	
import multiprocessing
import sqlite3
import traceback

app = Flask(__name__)
CORS(app)

#CORS(app, resources={r"/": {"origins": ""}})

UPLOAD_FOLDER = 'Backend'
ALLOWED_EXTENSIONS = {'xlsx', 'xls'}

app.config['UPLOAD_FOLDER'] = UPLOAD_FOLDER
stop_process = False

JOB_DB_PATH = os.path.join(os.path.dirname(__file__), "jobs.db")
SERVER_INSTANCE_ID = str(uuid.uuid4())

def _job_db_connect():
    con = sqlite3.connect(JOB_DB_PATH, check_same_thread=False)
    con.row_factory = sqlite3.Row
    return con

def _job_db_init():
    con = _job_db_connect()
    try:
        con.execute(
            """
            CREATE TABLE IF NOT EXISTS jobs (
                job_id TEXT PRIMARY KEY,
                client_id TEXT,
                endpoint TEXT,
                status TEXT,
                message TEXT,
                created_at TEXT,
                updated_at TEXT,
                result_json TEXT,
                error TEXT
            )
            """
        )
        cols = [r["name"] for r in con.execute("PRAGMA table_info(jobs)").fetchall()]
        if "server_instance_id" not in cols:
            con.execute("ALTER TABLE jobs ADD COLUMN server_instance_id TEXT")
        if "payload" not in cols:
            con.execute("ALTER TABLE jobs ADD COLUMN payload TEXT")
        con.execute("CREATE INDEX IF NOT EXISTS idx_jobs_client_status ON jobs(client_id, status)")
        con.execute("CREATE INDEX IF NOT EXISTS idx_jobs_instance_status ON jobs(server_instance_id, status)")
        con.commit()
    finally:
        con.close()

def _job_prune_old(days: int = 30):
    con = _job_db_connect()
    try:
        from datetime import timedelta, timezone
        cutoff = (datetime.now(timezone.utc) - timedelta(days=days)).strftime("%Y-%m-%dT%H:%M:%SZ")
        con.execute(
            "DELETE FROM jobs WHERE status IN ('completed','failed') AND updated_at < ?",
            (cutoff,),
        )
        con.commit()
    finally:
        con.close()

def _job_reconcile_after_restart():
    con = _job_db_connect()
    try:
        ts = _job_now_iso()
        con.execute(
            """
            UPDATE jobs
            SET status='failed',
                message='server restarted',
                updated_at=?,
                error=COALESCE(error,'') || CASE WHEN error IS NULL OR error='' THEN '' ELSE '\n' END || 'Server restarted; background worker no longer running.'
            WHERE status IN ('queued','running')
              AND (server_instance_id IS NULL OR server_instance_id != ?)
            """,
            (ts, SERVER_INSTANCE_ID),
        )
        con.commit()
    finally:
        con.close()

def _job_now_iso():
    from datetime import timezone
    return datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")

def _job_create(client_id: str, endpoint: str, message: str = "queued", payload: str = None) -> str:
    job_id = str(uuid.uuid4())
    ts = _job_now_iso()
    con = _job_db_connect()
    try:
        con.execute(
            "INSERT INTO jobs(job_id, client_id, endpoint, server_instance_id, status, message, created_at, updated_at, payload) VALUES(?,?,?,?,?,?,?,?,?)",
            (job_id, client_id, endpoint, SERVER_INSTANCE_ID, "queued", message, ts, ts, payload),
        )
        con.commit()
    finally:
        con.close()
    return job_id

def _job_update(job_id: str, *, status: str = None, message: str = None, result_json: str = None, error: str = None, server_instance_id: str = None):
    fields = []
    values = []
    if status is not None:
        fields.append("status=?")
        values.append(status)
    if message is not None:
        fields.append("message=?")
        values.append(message)
    if result_json is not None:
        fields.append("result_json=?")
        values.append(result_json)
    if error is not None:
        fields.append("error=?")
        values.append(error)
    if server_instance_id is not None:
        fields.append("server_instance_id=?")
        values.append(server_instance_id)
    fields.append("updated_at=?")
    values.append(_job_now_iso())
    values.append(job_id)
    con = _job_db_connect()
    try:
        con.execute(f"UPDATE jobs SET {', '.join(fields)} WHERE job_id=?", values)
        con.commit()
    finally:
        con.close()

def _job_get(job_id: str):
    con = _job_db_connect()
    try:
        cur = con.execute("SELECT * FROM jobs WHERE job_id=?", (job_id,))
        row = cur.fetchone()
        return dict(row) if row else None
    finally:
        con.close()

def _job_get_active_for_client(client_id: str, endpoint: str = None):
    con = _job_db_connect()
    try:
        if endpoint:
            cur = con.execute(
                "SELECT * FROM jobs WHERE client_id=? AND endpoint=? AND status IN ('queued','running') ORDER BY created_at DESC LIMIT 1",
                (client_id, endpoint),
            )
        else:
            cur = con.execute(
                "SELECT * FROM jobs WHERE client_id=? AND status IN ('queued','running') ORDER BY created_at DESC LIMIT 1",
                (client_id,),
            )
        row = cur.fetchone()
        return dict(row) if row else None
    finally:
        con.close()



@app.route('/job_status/<job_id>', methods=['GET'])
def job_status(job_id):
    job = _job_get(job_id)
    if not job:
        return jsonify({"status": 0, "message": "job not found", "job_id": job_id}), 404
    return jsonify({"status": 1, "job": job})

@app.route('/job_result/<job_id>', methods=['GET'])
def job_result(job_id):
    job = _job_get(job_id)
    if not job:
        return jsonify({"status": 0, "message": "job not found", "job_id": job_id}), 404
    if job.get("status") != "completed":
        return jsonify({"status": 0, "message": "job not completed", "job_id": job_id, "job_status": job.get("status")}), 409
    return app.response_class(job.get("result_json") or "", mimetype="application/json")

@app.route('/active_job', methods=['GET'])
def active_job():
    client_id = request.args.get("client_id") or request.args.get("user") or ""
    endpoint = request.args.get("endpoint")
    if not client_id:
        return jsonify({"status": 0, "message": "client_id is required"}), 400
    job = _job_get_active_for_client(client_id, endpoint=endpoint)
    return jsonify({"status": 1, "job": job})

def _run_processfile_in_background(job_id: str, form_data: dict):
    try:
        _job_update(job_id, status="running", message="processing started")
        safe_data = dict(form_data or {})
        safe_data["async"] = "0"
        safe_data["job_id"] = job_id
        with app.test_request_context('/processFile', method='POST', data=safe_data):
            resp = processFile()
        if isinstance(resp, tuple):
            resp = resp[0]
        result_text = resp.get_data(as_text=True) if hasattr(resp, "get_data") else str(resp)
        try:
            res_val = json.loads(result_text)
            if res_val.get("status") == 0:
                _job_update(job_id, status="failed", message=res_val.get("message", "processing failed"), result_json=result_text)
                return
        except:
            pass
        _job_update(job_id, status="completed", message="processing completed", result_json=result_text)
    except Exception as e:
        _job_update(job_id, status="failed", message=str(e), error=traceback.format_exc())

def _run_processfileLeg1_in_background(job_id: str, form_data: dict):
    try:
        _job_update(job_id, status="running", message="processing started")
        safe_data = dict(form_data or {})
        safe_data["async"] = "0"
        safe_data["job_id"] = job_id
        with app.test_request_context('/processFileleg1', method='POST', data=safe_data):
            resp = processFile_leg1()
        if isinstance(resp, tuple):
            resp = resp[0]
        result_text = resp.get_data(as_text=True) if hasattr(resp, "get_data") else str(resp)
        try:
            res_val = json.loads(result_text)
            if res_val.get("status") == 0:
                _job_update(job_id, status="failed", message=res_val.get("message", "processing failed"), result_json=result_text)
                return
        except:
            pass
        _job_update(job_id, status="completed", message="processing completed", result_json=result_text)
    except Exception as e:
        _job_update(job_id, status="failed", message=str(e), error=traceback.format_exc())

def _run_job_from_cli(job_id: str, parent_instance_id: str = None):
    try:
        job = _job_get(job_id)
        if not job:
            print(f"Job {job_id} not found in DB")
            return
        
        payload_str = job.get("payload")
        form_data = json.loads(payload_str) if payload_str else {}
        
        # Update server_instance_id to the PARENT Flask server's ID so that
        # _job_reconcile_after_restart() on the Flask server does NOT kill this job
        _job_update(job_id, status="running", message="processing started",
                    server_instance_id=parent_instance_id or job.get("server_instance_id"))
        
        safe_data = dict(form_data or {})
        safe_data["async"] = "0"
        safe_data["job_id"] = job_id
        
        endpoint = job.get("endpoint")
        if endpoint == "/processFile":
            with app.test_request_context('/processFile', method='POST', data=safe_data):
                resp = processFile()
        elif endpoint == "/processFileleg1":
            with app.test_request_context('/processFileleg1', method='POST', data=safe_data):
                resp = processFile_leg1()
        else:
            raise ValueError(f"Unknown endpoint: {endpoint}")
            
        if isinstance(resp, tuple):
            resp = resp[0]
        result_text = resp.get_data(as_text=True) if hasattr(resp, "get_data") else str(resp)
        try:
            res_val = json.loads(result_text)
            if res_val.get("status") == 0:
                _job_update(job_id, status="failed", message=res_val.get("message", "processing failed"), result_json=result_text)
                return
        except Exception as pe:
            print("Failed to parse result json:", pe)
            
        _job_update(job_id, status="completed", message="processing completed", result_json=result_text)
        
    except Exception as e:
        import traceback
        _job_update(job_id, status="failed", message=str(e), error=traceback.format_exc())

def is_job_cancelled():
    job_id = request.form.get("job_id")
    if job_id:
        job = _job_get(job_id)
        if job and job.get("status") in ["cancelled", "failed"]:
            return True
    return False

@app.after_request
def remove_server_header(response):
    response.headers["Server"] = "Hidden"
    return response

def count_distinct_months(input_str):
    months_list = [month.strip() for month in input_str.split(',')]
    unique_months_count = len(set(months_list))
    return unique_months_count

def generate_random_id(length=14):
    alphabet = string.ascii_letters + string.digits
    random_id = ''.join(secrets.choice(alphabet) for _ in range(length))
    return random_id

def connect_to_database():
    host = 'localhost'
    user = 'root'
    password = ''
    database = 'bihar'
    connection = mysql.connector.connect(
        host=host, user=user, password=password, database=database
    )
    return connection
    
def write_log(message, log_directory='logs'):
    # Ensure log directory exists
    if not os.path.exists(log_directory):
        os.makedirs(log_directory, mode=0o755, exist_ok=True)

    # Get current year, month, and day
    now = datetime.now()
    year = now.strftime('%Y')
    month = now.strftime('%m')
    day = now.strftime('%d')

    # Construct the directory structure (year/month)
    year_directory = os.path.join(log_directory, year)
    month_directory = os.path.join(year_directory, month)

    os.makedirs(year_directory, mode=0o755, exist_ok=True)
    os.makedirs(month_directory, mode=0o755, exist_ok=True)

    # Define the log file path (year/month/day.log)
    log_file_path = os.path.join(month_directory, f"{day}.log")

    # Format the log message with a timestamp
    timestamp = now.strftime('%Y-%m-%d %H:%M:%S')
    formatted_message = f"[{timestamp}] {message}\n"

    # Write the log message to the file
    with open(log_file_path, 'a') as log_file:
        log_file.write(formatted_message)    


def allowed_file(filename):
    return '.' in filename and filename.rsplit('.', 1)[1].lower() in ALLOWED_EXTENSIONS


@app.route('/')
def hello():
    return 'Hi, PDS!'
    
def read_protected_excel(file_path, password, sheet_name=None):
    with open(file_path, 'rb') as file:
        file_decryptor = msoffcrypto.OfficeFile(file)
        file_decryptor.load_key(password=password)  # Provide the password here

        # Create a BytesIO buffer to store the decrypted content
        decrypted = BytesIO()
        file_decryptor.decrypt(decrypted)

        # Read the specified sheet or all sheets from the decrypted content
        dfs = pd.read_excel(decrypted, sheet_name=sheet_name, engine='openpyxl')

    return dfs        
            


@app.route('/get_users', methods=['GET'])
def get_users():
    if request.method == 'GET':
        connection = connect_to_database()
        user_list = []

        if connection.is_connected():
            cursor = connection.cursor()
            query = 'SELECT * FROM login WHERE 1'
            cursor.execute(query)
            user = cursor.fetchall()
            connection.close()

            if user:
                for row in user:
                    temp = {'username': row[0], 'password': row[1], '_id': row[2]}
                    user_list.append(temp)
                return jsonify(user_list)
            else:
                return jsonify(user_list)
        else:
            return jsonify(user_list)

@app.route('/extract_db', methods=['POST'])
def extract_db():
    if request.method == 'POST':
        connection = connect_to_database()
        warehouse_data = []
        fps_data = []
        all_data = {}
        applicableCount = request.form.get('applicable')

        if connection.is_connected():
            cursor = connection.cursor()
            query = "SELECT * FROM warehouse WHERE active='1'"
            cursor.execute(query)
            user = cursor.fetchall()


            if user:
                for row in user:
                    temp = {
                        'State Name': '',
                        'WH_District': row[0] if row[0] else '',
                        'WH_Name': row[1] if row[1] else '',
                        'WH_ID': row[2] if row[2] is not None else 0,
                        'Type of WH': row[3] if row[3] else '',
                        'WH_Lat': float(row[5]) if row[5] is not None else 0,
                        'WH_Long': float(row[6]) if row[6] is not None else 0,
                        'Storage_Capacity': float(row[7]) if row[7] is not None else 0,
                        'Owned/Rented': '',
                        'quantity of Wheat stored (Quintals)': 0
                    }
                    warehouse_data.append(temp)

        # -------- FPS --------
        if connection.is_connected():
            cursor = connection.cursor()
            query = "SELECT * FROM fps WHERE active='1'"
            cursor.execute(query)
            user = cursor.fetchall()

            if user:
                for row in user:
                    temp = {
                        'State Name': '',
                        'FPS_District': row[0] if row[0] else '',
                        'FPS_Name': row[1] if row[1] else '',
                        'FPS_ID': row[2] if row[2] is not None else 0,
                        'Motorable/Non-Motorable': row[3] if row[3] else '',
                        'FPS_Lat': float(row[4]) if row[4] is not None else 0,
                        'FPS_Long': float(row[5]) if row[5] is not None else 0,
                        'Allocation_Wheat': (float(row[6]) if row[6] is not None else 0) * int(applicableCount),
                        'Allocation_FRice': (float(row[9]) if row[9] is not None else 0) * int(applicableCount),
                        'FPS_Tehsil': ''
                    }
                    fps_data.append(temp)

            

            
            
                
            all_data["warehouse"] = warehouse_data
            all_data["fps"] = fps_data
            json_file_path = 'output.json'
            with open(json_file_path, 'w') as json_file:
                json.dump(all_data, json_file, indent=2)
        else:
            json_file_path = 'output.json'
            with open(json_file_path, 'w') as json_file:
                json.dump(all_data, json_file, indent=2)
        
        json_file_path = 'output.json'
        with open(json_file_path, 'r') as json_file:
            data = json.load(json_file)

        wh = pd.DataFrame(data['warehouse'])
        fps = pd.DataFrame(data['fps'])
        wh = wh.loc[:,["State Name","WH_District",'WH_Name',"WH_ID","Type of WH",'WH_Lat',"WH_Long","Storage_Capacity","Owned/Rented","quantity of Wheat stored (Quintals)"]]
        fps = fps.loc[:,["State Name","FPS_District",'FPS_Name',"FPS_ID","Motorable/Non-Motorable",'FPS_Lat',"FPS_Long","Allocation_Wheat","Allocation_FRice","FPS_Tehsil"]]

        # Rename the columns to make them valid Python identifiers
        column_mapping = {
            'Type of WH': 'Type of WH ( SWC, CWC, FCI, CAP, other)',
            'Storage_Capacity': 'Storage_Capacity',
            'WH_District': 'WH_District',
            'WH_ID': 'WH_ID',
            'WH_Lat': 'WH_Lat',
            'WH_Long': 'WH_Long',
            'WH_Name': 'WH_Name'
        }

        wh.rename(columns=column_mapping, inplace=True)
        wh.rename(columns=column_mapping, inplace=True)
        wh_filtered = wh[wh["Type of WH ( SWC, CWC, FCI, CAP, other)"] != 'fci']
        
        
        def convert_to_numeric(value):
            try:
                return pd.to_numeric(value)
            except ValueError:
                return value

        # Apply the function to the DataFrame
        wh_filtered['WH_ID'] = wh['WH_ID'].apply(convert_to_numeric)
        fps['FPS_ID'] = fps['FPS_ID'].apply(convert_to_numeric)
        
        wh_filtered = wh_filtered.drop_duplicates(subset=['WH_ID'], keep='first')
        fps = fps.drop_duplicates(subset=['FPS_ID'], keep='first') 
        

        # Save DataFrames to Excel file
        with pd.ExcelWriter('Backend//Data_1.xlsx') as writer:
            wh_filtered.to_excel(writer, sheet_name='A.1 Warehouse', index=False)
            fps.to_excel(writer, sheet_name='A.2 FPS', index=False)

        return {"success":1}
        
@app.route('/extract_data', methods=['POST'])
def extract_data():
    if request.method == 'POST':
        try:
            connection = connect_to_database()
            tablename = ""
            data = []
            fci_data=[]
            dcp_data=[]
            
            
            if connection.is_connected():
                cursor = connection.cursor()
                query = "SELECT id FROM optimised_table ORDER BY last_updated DESC LIMIT 1"
                cursor.execute(query)
                ids = cursor.fetchall()
                for id_ in ids:
                    tablename = "optimiseddata_" + id_[0]
            

            if connection.is_connected():
                cursor = connection.cursor()

                # -------- FCI --------
                query = "SELECT * FROM fci WHERE active='1'"
                cursor.execute(query)
                user = cursor.fetchall()

                if user:
                    for row in user:
                        temp = {
                            'State Name': '',
                            'WH_District': row[0] if row[0] else '',
                            'WH_Name': row[1] if row[1] else '',
                            'WH_ID': int(row[2]) if row[2] is not None else 0,
                            'Type of WH': row[3] if row[3] else '',
                            'WH_Lat': float(row[4]) if row[4] is not None else 0,
                            'WH_Long': float(row[5]) if row[5] is not None else 0,
                            'Allotment_Wheat': float(row[6]) if row[6] is not None else 0,
                            'Allotment_FRice': float(row[9]) if row[9] is not None else 0,
                            'Owned/Rented': '',
                            'quantity of Wheat stored (Quintals)': 0
                        }
                        fci_data.append(temp)

                # -------- DCP --------
                query = "SELECT * FROM dcp WHERE active='1' "
                cursor.execute(query)
                user = cursor.fetchall()

                if user:
                    for row in user:
                        temp = {
                            'State Name': '',
                            'WH_District': row[0] if row[0] else '',
                            'WH_Name': row[1] if row[1] else '',
                            'WH_ID': int(row[2]) if row[2] is not None else 0,
                            'Type of WH': row[3] if row[3] else '',
                            'WH_Lat': float(row[4]) if row[4] is not None else 0,
                            'WH_Long': float(row[5]) if row[5] is not None else 0,
                            'Procurement Rice': float(row[6]) if row[6] is not None else 0,
                            'Procurement Wheat': float(row[9]) if row[9] is not None else 0,
                            'quantity of Wheat stored (Quintals)': 0
                        }
                        dcp_data.append(temp)

                        
                
                cursor = connection.cursor()
                query = "SELECT * FROM {}".format(tablename)
                
                cursor.execute(query)
                result = cursor.fetchall()
                columns = ["From ID", "From name", "from district", "from lat", "from long","commodity","quantity"]
                tableData = [columns]
                

                for row in result:
                    #print(row)
                    if row[20] != "" and row[20] is not None:
                        id = row[20]
                        query_warehouse = "SELECT latitude, longitude, district FROM warehouse WHERE id=%s"
                        cursor.execute(query_warehouse, (id,))
                        result_warehouse = cursor.fetchone()
                        if result_warehouse:
                            row = list(row)
                            row[6], row[7], row[5] = result_warehouse
                            row[3] = row[20]
                            row[4] = row[22]
                            row[17] = row[26]
                    elif row[21] != "" and row[21] is not None and row[19] == "yes":
                        id = row[21]
                        query_warehouse = "SELECT latitude, longitude, district FROM warehouse WHERE id=%s"
                        cursor.execute(query_warehouse, (id,))
                        result_warehouse = cursor.fetchone()
                        if result_warehouse:
                            row = list(row)
                            row[6], row[7], row[5] = result_warehouse
                            row[3] = row[21]
                            row[4] = row[23]
                            row[17] = row[27]
                          

                    #tableData.append(list(row))
                    data.append({
                                "From ID": row[3],
                                "From name": row[4],
                                "from district": row[5],
                                "from lat": row[6],
                                "from long": row[7],
                                "commodity":row[15],
                                "quantity": row[16]
                            })
                response = {}
                response['status'] = 1
                response['data'] = data
                response['fci_data'] = fci_data
                response['dcp_data'] = dcp_data
                json_file_path = 'output_fci.json'
                with open(json_file_path, 'w') as json_file:
                    json.dump(response, json_file, indent=2)
                    
                json_file_path = 'output_fci.json'
                with open(json_file_path, 'r') as json_file:
                   data = json.load(json_file)
                    
    
                wh = pd.DataFrame(data['data'])
                fci = pd.DataFrame(data['fci_data'])   
                dcp = pd.DataFrame(data['dcp_data'])   
               

                wh = wh.loc[:,["From ID","From name",'from district',"from lat","from long","commodity","quantity"]]
                fci = fci.loc[:,["State Name","WH_District",'WH_Name',"WH_ID","Type of WH",'WH_Lat',"WH_Long","Allotment_Wheat","Allotment_FRice"]]    
                dcp = dcp.loc[:,["State Name","WH_District",'WH_Name',"WH_ID","Type of WH",'WH_Lat',"WH_Long","Procurement Rice","Procurement Wheat"]]    

                column_mapping = {
                            'From ID': 'SW_ID',
                            'From name': 'SW_Name',
                            'from district': 'SW_District',
                            'from lat': 'SW_lat',
                            'from long': 'SW_Long',
                           
                        }                
                
                wh.rename(columns=column_mapping, inplace=True)
                wh['quantity'] = wh['quantity'].apply(pd.to_numeric, errors='coerce')
                
                wh.rename(columns=column_mapping, inplace=True)
                wh['quantity'] = wh['quantity'].apply(pd.to_numeric, errors='coerce')
                
                has_bf = 'Wheat' in wh['commodity'].unique()
                has_rf = 'FRice' in wh['commodity'].unique()
                
                wh = wh.pivot_table(index=['SW_ID', 'SW_Name', 'SW_District', 'SW_lat', 'SW_Long'], columns='commodity', values='quantity', aggfunc='sum').reset_index()
                wh.fillna(0, inplace=True)
                wh.index.name = None
                
                
                
                
                
                # Rename only if present, otherwise add as 0
                if has_bf:
                    wh.rename(columns={'FRice': 'Demand_FRice'}, inplace=True)
                else:
                    wh['Demand_FRice'] = 0

                if has_rf:
                    wh.rename(columns={'Wheat': 'Demand_Wheat'}, inplace=True)
                else:
                    wh['Demand_Wheat'] = 0
                    
                print(wh)    

                

                def convert_to_numeric(value):
                    try:
                        return pd.to_numeric(value)
                    except ValueError:
                        return value

                # Apply the function to the DataFrame
                wh['SW_ID'] = wh['SW_ID'].apply(convert_to_numeric)
                fci['WH_ID'] = fci['WH_ID'].apply(convert_to_numeric)
                dcp['WH_ID'] = dcp['WH_ID'].apply(convert_to_numeric)
                
                
                with pd.ExcelWriter('Backend//Data_2.xlsx') as writer:
                    wh.to_excel(writer, sheet_name='A.1 Warehouse', index=False)
                    fci.to_excel(writer, sheet_name='A.2 FCI', index=False)
                    dcp.to_excel(writer, sheet_name='A.2 DCP', index=False)
                #print("Shallu")

                return response
            else:
                return {"success": 0, "message": "Database connection failed"}
        except Exception as e:
            return {"success": 0, "message": str(e)}
    else:
        return {"success": 0, "message": "Invalid request method"}
        
@app.route('/fetchdatafromsql', methods=['GET'])        
def fetch_data_from_sql():
    if request.method == 'GET':
        connection = connect_to_database()
        if connection.is_connected():
            cursor = connection.cursor()
            query = "SELECT * FROM optimised_table"
            cursor.execute(query)
            data = cursor.fetchall()
            cursor.close()
            connection.close()
            df = pd.DataFrame(data, columns=['id', 'month', 'year', 'applicable', 'data', 'last_updated', 'rolled_out', 'cost'])
            df_first_4_columns = df[['id', 'month', 'year', 'applicable']]
            # Convert selected columns to JSON string
            json_data = df_first_4_columns.to_json(orient='records')
            return json_data
        else:
            print("Error: Unable to connect to the database")
            return jsonify({"error": "Unable to connect to the database"})
    else:
        return jsonify({"error": "Request method is not GET"})

@app.route('/uploadConfigExcel', methods=['POST'])
def upload_config_excel():
    data = {}
    try:
        file = request.files['uploadFile']
        if file and allowed_file(file.filename):
            file_path = os.path.join(app.config['UPLOAD_FOLDER'], 'Data_1.xlsx')
            os.makedirs(app.config['UPLOAD_FOLDER'], exist_ok=True)
            file.save(file_path)
            data['status'] = 1
            df = pd.read_excel(file_path)
        else:
            data['status'] = 0
            data['message'] = 'Invalid file. Only .xlsx or .xls files are allowed.'
    except Exception as e:
        data['status'] = 0
        data['message'] = 'Error uploading file'
        
        
    input = pd.ExcelFile('Backend//Data_1.xlsx')
    node1 = pd.read_excel(input,sheet_name="A.1 Warehouse")
    node2 = pd.read_excel(input,sheet_name="A.2 FPS")
    dist = [[0 for a in range(len(node2["FPS_ID"]))] for b in range(len(node1["WH_ID"]))]
    phi_1 = []
    phi_2 = []
    delta_phi = []
    delta_lambda = []
    R = 6371 

    for i in node1.index:
        for j in node2.index:
            phi_1=math.radians(node1["WH_Lat"][i])
            phi_2=math.radians(node2["FPS_Lat"][j])
            delta_phi=math.radians(node2["FPS_Lat"][j]-node1["WH_Lat"][i])
            delta_lambda=math.radians(node2["FPS_Long"][j]-node1["WH_Long"][i])
            delta_lambda=math.radians(node2["FPS_Long"][j]-node1["WH_Long"][i])
            x=math.sin(delta_phi / 2.0) ** 2 + math.cos(phi_1) * math.cos(phi_2) * math.sin(delta_lambda / 2.0) ** 2
            y=2 * math.atan2(math.sqrt(x), math.sqrt(1 - x))
            dist[i][j]=R*y
            
    dist=np.transpose(dist)
    df3 = pd.DataFrame(data = dist, index = node2['FPS_ID'], columns = node1['WH_ID'])
    df3.to_excel('Backend//Distance_Matrix.xlsx', index=True)
    return jsonify(data)



@app.route('/getfcidata', methods=['POST'])
def fci_data():
    try:
        usn = pd.ExcelFile('Backend//Data_1.xlsx')
        fci = pd.read_excel(usn, sheet_name='A.1 Warehouse', index_col=None)
        fps = pd.read_excel(usn, sheet_name='A.2 FPS', index_col=None)
       
        warehouse_no = fci['WH_ID'].nunique()
        fps_no = fps['FPS_ID'].nunique()
        combined_districts = pd.concat([fci['WH_District'],fps['FPS_District']])
        districts_no = combined_districts.nunique()
        total_demand = float(fps['Allocation_Wheat'].sum())
        total_demand_rice = float(fps['Allocation_FRice'].sum())
        total_supply = float(fci['Storage_Capacity'].sum())

        result = {'Warehouse_No': warehouse_no, 'FPS_No': fps_no, 'Total_Demand': total_demand,'Total_Demand_Rice': total_demand_rice, 'Total_Supply': total_supply, 'District_Count': districts_no}
        return jsonify(result)
        #print(result)
    except Exception as e:
        return jsonify({'status': 0, 'message': str(e)})

@app.route('/getfcidataleg1', methods=['POST'])
def fci_dataleg1():
    try:
        usn = pd.ExcelFile('Backend//Data_2.xlsx')
        wh = pd.read_excel(usn, sheet_name='A.1 Warehouse', index_col=None)
        fci = pd.read_excel(usn, sheet_name='A.2 FCI', index_col=None)
        dcp = pd.read_excel(usn, sheet_name='A.2 DCP', index_col=None)
        
        warehouse_no = fci['WH_ID'].nunique()
        fps_no = wh["SW_ID"].nunique()
        dcp_no = dcp["WH_ID"].nunique()
        combined_districts = pd.concat([fci['WH_District'],wh['SW_District']])
        districts_no = combined_districts.nunique()
        total_demand = float(wh['Demand_Wheat'].sum())
        total_demand_rice = float(wh['Demand_FRice'].sum())
        total_riceprocurement = float(dcp['Procurement Rice'].sum())
        total_wheatprocurement = float(dcp['Procurement Wheat'].sum())
        total_supply = float(fci['Allotment_Wheat'].sum())
        total_supply1 = float(fci['Allotment_FRice'].sum())
       
        

        result = {'Warehouse_No': warehouse_no, 'FPS_No': fps_no, 'Total_Demand': total_demand, 'Total_Supply': total_supply, 'District_Count': districts_no,'Total_Demand_Rice': total_demand_rice,'Rice_Procurement':total_riceprocurement,'Wheat_Procurement':total_wheatprocurement, 'DCP_No':dcp_no,'Total_Supply1': total_supply1,}
        
        print(result)
        
        
        return jsonify(result)
    except Exception as e:
        return jsonify({'status': 0, 'message': str(e)})


@app.route('/getGraphData', methods=['POST'])
def graph_data():
    try:
        usn = pd.ExcelFile('Backend//Data_1.xlsx')
        FCI = pd.read_excel(usn, sheet_name='A.1 Warehouse', index_col=None)
        FPS = pd.read_excel(usn, sheet_name='A.2 FPS', index_col=None)


        
        District_Capacity = {}
        for i in range(len(FCI["WH_District"])):
            District_Name = FCI["WH_District"][i]
            if District_Name not in District_Capacity:
                District_Capacity[District_Name] = float(FCI["Storage_Capacity"][i])
            else:
                District_Capacity[District_Name] += float(FCI["Storage_Capacity"][i])

        

                
        District_Demand = {}
        for i in range(len(FPS["FPS_District"])):
            District_Name_FPS = FPS["FPS_District"][i]
            if District_Name_FPS not in District_Demand:
                District_Demand[District_Name_FPS] = float(FPS["Allocation_Wheat"][i])
            else:
                District_Demand[District_Name_FPS] += float(FPS["Allocation_Wheat"][i])
                
        District_Demand_Rice = {}
        for i in range(len(FPS["FPS_District"])):
            District_Name_FPS = FPS["FPS_District"][i]
            if District_Name_FPS not in District_Demand_Rice:
                District_Demand_Rice[District_Name_FPS] = float(FPS["Allocation_FRice"][i])
            else:
                District_Demand_Rice[District_Name_FPS] += float(FPS["Allocation_FRice"][i])
                
        District_Demand_Total = {}
        for i in range(len(FPS["FPS_District"])):
            District_Name_FPS = FPS["FPS_District"][i]
            if District_Name_FPS not in District_Demand_Total:
                District_Demand_Total[District_Name_FPS] = float(FPS["Allocation_Wheat"][i])+float(FPS["Allocation_FRice"][i])
            else:
                District_Demand_Total[District_Name_FPS] += float(FPS["Allocation_Wheat"][i])+float(FPS["Allocation_FRice"][i])

                
        District_Name = []
        District_Name2=[]
        District_Name = [i for i in District_Demand_Total if i not in District_Capacity]
        District_Name2 = [i for i in District_Demand_Total if i in District_Capacity and District_Demand_Total[i] >= District_Capacity[i]]
        District_Name_1 = {}
        District_Name_1['District_Name_All'] = District_Name + District_Name2
        District_Name3 = [i for i in District_Demand_Total if i in District_Capacity and District_Demand_Total[i] <= District_Capacity[i]]

        


        
        combined_data = {'District_Demand': District_Demand, 'District_Capacity': District_Capacity, 'District_Name': District_Name_1,'District_Demand_Rice': District_Demand_Rice,}
        
        
        return jsonify(combined_data)
    except Exception as e:
        return jsonify({'status': 0, 'message': str(e)})
        
@app.route('/getGraphDataleg1', methods=['POST'])
def graph_dataleg1():
    try:
        usn = pd.ExcelFile('Backend//Data_2.xlsx')
        wh = pd.read_excel(usn, sheet_name='A.1 Warehouse', index_col=None)
        fci = pd.read_excel(usn, sheet_name='A.2 FCI', index_col=None)
        dcp = pd.read_excel(usn, sheet_name='A.2 DCP', index_col=None)
        
        


        
        District_Capacity = {}
        for i in range(len(fci["WH_District"])):
            District_Name = fci["WH_District"][i]
            if District_Name not in District_Capacity:
                District_Capacity[District_Name] = float(fci["Allotment_Wheat"][i])
            else:
                District_Capacity[District_Name] += float(fci["Allotment_Wheat"][i])
                
        District_Capacity1 = {}
        for i in range(len(fci["WH_District"])):
            District_Name1 = fci["WH_District"][i]
            if District_Name1 not in District_Capacity1:
                District_Capacity1[District_Name1] = float(fci["Allotment_FRice"][i])
            else:
                District_Capacity1[District_Name1] += float(fci["Allotment_FRice"][i])
        

        District_Demand = {}
        for i in range(len(wh["SW_District"])):
            District_Name_FPS = wh["SW_District"][i]
            if District_Name_FPS not in District_Demand:
                District_Demand[District_Name_FPS] = float(wh["Demand_Wheat"][i])
            else:
                District_Demand[District_Name_FPS] += float(wh["Demand_Wheat"][i])
                
       
                
        District_Demand_Rice = {}
        for i in range(len(wh["SW_District"])):
            District_Name_FPS = wh["SW_District"][i]
            if District_Name_FPS not in District_Demand_Rice:
                District_Demand_Rice[District_Name_FPS] = float(wh["Demand_FRice"][i])
            else:
                District_Demand_Rice[District_Name_FPS] += float(wh["Demand_FRice"][i])
       
        
        
                
        State_Riceprocurement = {}
        for i in range(len(dcp["WH_District"])):
            District_Name = dcp["WH_District"][i]
            if District_Name not in State_Riceprocurement:
                State_Riceprocurement[District_Name] = float(dcp["Procurement Rice"][i])
            else:
                State_Riceprocurement[District_Name] += float(dcp["Procurement Rice"][i])
                
        State_Wheatprocurement = {}
        for i in range(len(dcp["WH_District"])):
            District_Name = dcp["WH_District"][i]
            if District_Name not in State_Wheatprocurement:
                State_Wheatprocurement[District_Name] = float(dcp["Procurement Wheat"][i])
            else:
                State_Wheatprocurement[District_Name] += float(dcp["Procurement Wheat"][i])
                
       
        
        District_Name = []
        District_Name2=[]
        District_Name = [i for i in District_Demand if i not in State_Wheatprocurement]
        District_Name2 = [i for i in District_Demand if i in State_Wheatprocurement and District_Demand[i] >= State_Wheatprocurement[i]]
        District_Name_1 = {}
        District_Name_1['District_Name_All'] = District_Name + District_Name2
        District_Name3 = [i for i in District_Demand if i in State_Wheatprocurement and District_Demand[i] <= State_Wheatprocurement[i]]
        
        
        District_Name1 = []
        District_Name21=[]
        District_Name1 = [i for i in District_Demand_Rice if i not in State_Riceprocurement]
        District_Name21 = [i for i in District_Demand_Rice if i in State_Riceprocurement and District_Demand_Rice[i] >= State_Riceprocurement[i]]
        District_Name_11 = {}
        District_Name_11['District_Name_All1'] = District_Name1 + District_Name21
        District_Name31 = [i for i in District_Demand_Rice if i in State_Riceprocurement and District_Demand_Rice[i] <= State_Riceprocurement[i]]

        


        
        combined_data = {'District_Demand': District_Demand, 'District_Capacity': District_Capacity, 'District_Name': District_Name_1,'District_Demand_Rice': District_Demand_Rice,'State_Riceprocurement': State_Riceprocurement,'State_Wheatprocurement': State_Wheatprocurement,'District_Capacity1': District_Capacity1, 'District_Name1': District_Name_11,}
        
        
        
        return jsonify(combined_data)
    except Exception as e:
        return jsonify({'status': 0, 'message': str(e)})



def check_id_exists(connection, random_id):
    cursor = connection.cursor()
    query = "SELECT COUNT(*) FROM optimised_table WHERE id = %s"
    cursor.execute(query, (random_id,))
    result = cursor.fetchone()[0]
    return result > 0
    
def check_id_exists_leg1(connection, random_id):
    cursor = connection.cursor()
    query = "SELECT COUNT(*) FROM optimised_table_leg1 WHERE id = %s"
    cursor.execute(query, (random_id,))
    result = cursor.fetchone()[0]
    return result > 0   

def check_year_month_exists(connection, month, year):
    cursor = connection.cursor()
    query = "SELECT COUNT(*) FROM optimised_table WHERE month = %s and year = %s"
    cursor.execute(query, (month,year,))
    result = cursor.fetchone()[0]
    return result > 0
    
def check_year_month_exists_leg1(connection, month, year):
    cursor = connection.cursor()
    query = "SELECT COUNT(*) FROM optimised_table_leg1 WHERE month = %s and year = %s"
    cursor.execute(query, (month,year,))
    result = cursor.fetchone()[0]
    return result > 0

def get_year_month_exists(connection, month, year):
    cursor = connection.cursor()
    query = "SELECT id FROM optimised_table WHERE month = %s and year = %s"
    cursor.execute(query, (month,year,))
    result = cursor.fetchone()
    return result[0] if result else None
    
def get_year_month_exists_leg1(connection, month, year):
    cursor = connection.cursor()
    query = "SELECT id FROM optimised_table_leg1 WHERE month = %s and year = %s"
    cursor.execute(query, (month,year,))
    result = cursor.fetchone()
    return result[0] if result else None

#@app.route('/saveToDatabase', methods=['GET'])
def save_to_database(month, year, applicable):
    connection = connect_to_database()
    random_id = generate_random_id()
    while (check_id_exists(connection,random_id)):
        random_id = generate_random_id()
    table_name = "optimiseddata_" + str(random_id)
    warehouse_table = "warehouse_" + str(random_id)
    fps_table = "fps_" + str(random_id)
    dcp_table = "dcp_" + str(random_id)
    if connection.is_connected():
        cursor = connection.cursor()
        current_datetime = datetime.now()
        formatted_datetime = current_datetime.strftime("%Y-%m-%d %H:%M:%S")
        if(check_year_month_exists(connection, month, year)):
            existingid = get_year_month_exists(connection, month, year);
            sql = "UPDATE optimised_table set applicable='" + applicable + "', last_updated='" + formatted_datetime + "', cost = '""'  WHERE id='" + existingid + "'"; 
            table_name = "optimiseddata_" + str(existingid)
            warehouse_table = "warehouse_" + str(existingid)
            fps_table = "fps_" + str(existingid)
            dcp_table = "dcp_" + str(existingid)
            cursor.execute(sql)
        else:
            sql = "INSERT INTO optimised_table (id, month, year, applicable,last_updated) VALUES ('" + random_id + "','" + month + "','" + year + "','" + applicable + "','" + formatted_datetime + "')";
            cursor.execute(sql)
        
        connection.commit()
        warehouse_drop_query = 'DROP TABLE IF EXISTS ' + warehouse_table;
        cursor.execute(warehouse_drop_query)
        connection.commit()
        create_warehouse_query = ("CREATE TABLE " + warehouse_table + " (district VARCHAR(100) NOT NULL, name VARCHAR(100) NOT NULL, id VARCHAR(100) NOT NULL, warehousetype VARCHAR(100) NOT NULL, type VARCHAR(100) NOT NULL, latitude VARCHAR(100) NOT NULL, longitude VARCHAR(100) NOT NULL, storage VARCHAR(100) NOT NULL, uniqueid VARCHAR(100) NOT NULL, active VARCHAR(10) NOT NULL DEFAULT '1')")
        cursor.execute(create_warehouse_query)
        connection.commit()
        copy_warehouse_data = ("INSERT INTO " + warehouse_table + " SELECT * FROM warehouse WHERE active='1'")
        cursor.execute(copy_warehouse_data)
        connection.commit()
        
        fps_drop_query = 'DROP TABLE IF EXISTS ' + fps_table;
        cursor.execute(fps_drop_query)
        create_fps_query = ("CREATE TABLE " + fps_table + " (district VARCHAR(100) NOT NULL, name VARCHAR(100) NOT NULL, id VARCHAR(100) NOT NULL, type VARCHAR(100) NOT NULL, latitude VARCHAR(100) NOT NULL, longitude VARCHAR(100) NOT NULL,demand VARCHAR(100) NOT NULL,uniqueid VARCHAR(100) NOT NULL,active VARCHAR(10) NOT NULL DEFAULT '1', demand_rice VARCHAR(100) NOT NULL)")
        cursor.execute(create_fps_query)
        connection.commit()
        copy_fps_data = ("INSERT INTO " + fps_table + " SELECT * FROM fps WHERE active='1'")
        cursor.execute(copy_fps_data)
        connection.commit()
        
        dcp_drop_query = 'DROP TABLE IF EXISTS ' + dcp_table;
        cursor.execute(dcp_drop_query)
        create_dcp_query = ("CREATE TABLE " + dcp_table + " (district VARCHAR(100) NOT NULL, name VARCHAR(100) NOT NULL, id VARCHAR(100) NOT NULL, type VARCHAR(100) NOT NULL, latitude VARCHAR(100) NOT NULL, longitude VARCHAR(100) NOT NULL,demand VARCHAR(100) NOT NULL,uniqueid VARCHAR(100) NOT NULL, active VARCHAR(10) NOT NULL DEFAULT '1', demand_rice VARCHAR(100) NOT NULL)")
        cursor.execute(create_dcp_query)
        connection.commit()
        copy_dcp_data = ("INSERT INTO " + dcp_table + " SELECT * FROM dcp WHERE active='1'")
        cursor.execute(copy_dcp_data)
        connection.commit()
        
        
        excel_file_path = 'Backend//Result_Sheet.xlsx'
        columns_to_fetch = ['Scenario','From','From_State','From_ID','From_Name','From_District','From_Lat','From_Long','To','To_State','To_ID','To_Name', 'To_District', 'To_Lat', 'To_Long','commodity','quantity','Distance']
        df = pd.read_excel(excel_file_path)
        selected_data = df[columns_to_fetch]
        sql = 'DROP TABLE IF EXISTS ' + table_name;
        cursor.execute(sql)
        connection.commit()
        
        sql = "CREATE TABLE " + table_name + " ( scenario VARCHAR(150) NOT NULL, `from` VARCHAR(150) NOT NULL,from_state VARCHAR(150) NOT NULL, from_id VARCHAR(150) NOT NULL, from_name VARCHAR(150) NOT NULL, from_district VARCHAR(150) NOT NULL, from_lat VARCHAR(150) NOT NULL,from_long VARCHAR(150) NOT NULL, `to` VARCHAR(150) NOT NULL,to_state VARCHAR(150) NOT NULL,to_id VARCHAR(150) NOT NULL, to_name VARCHAR(150) NOT NULL, to_district VARCHAR(150) NOT NULL, to_lat VARCHAR(150) NOT NULL, to_long VARCHAR(150) NOT NULL, commodity VARCHAR(150) NOT NULL,quantity VARCHAR(150) NOT NULL, distance VARCHAR(150) NOT NULL, approve_admin VARCHAR(100) , approve_district VARCHAR(100) , new_id_admin VARCHAR(100), new_id_district VARCHAR(100) , new_name_admin VARCHAR(100) , new_name_district VARCHAR(10) , reason_admin VARCHAR(255) , reason_district VARCHAR(255), new_distance_admin VARCHAR(100), new_distance_district VARCHAR(100), district_change_approve VARCHAR(100), status VARCHAR(100) )";
        cursor.execute(sql)
        connection.commit()
        
        for (index, row) in selected_data.iterrows():
            sql = 'INSERT INTO ' + table_name + ' (`scenario`, `from`, `from_state`, `from_id`, `from_name`, `from_district`, `from_lat`, `from_long`, `to`, `to_state`, `to_id`, `to_name`, `to_district`, `to_lat`, `to_long`, `commodity`, `quantity`, `distance`) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)'
            values = tuple(row)
            cursor.execute(sql, values)
            connection.commit()
 
    if connection.is_connected():
        cursor.close()
        connection.close()
    return jsonify({'status': 1})
    
def save_to_database_leg1(month, year, applicable, scenario_type):
    connection = connect_to_database()
    random_id = generate_random_id()
    while (check_id_exists_leg1(connection,random_id)):
        random_id = generate_random_id()
    table_name = "optimiseddata_leg1_" + str(random_id)
    warehouse_table = "warehouse_leg1_" + str(random_id)
    fci_table = "fci_leg1_" + str(random_id)
    if connection.is_connected():
        cursor = connection.cursor()
        current_datetime = datetime.now()
        formatted_datetime = current_datetime.strftime("%Y-%m-%d %H:%M:%S")
        if(check_year_month_exists_leg1(connection, month, year)):
            existingid = get_year_month_exists_leg1(connection, month, year);
            sql = "UPDATE optimised_table_leg1 set applicable='" + applicable + "', last_updated='" + formatted_datetime + "', type='" + scenario_type + "', cost = '""'  WHERE id='" + existingid + "'"; 
            #print(sql)
            table_name = "optimiseddata_leg1_" + str(existingid)
            warehouse_table = "warehouse_leg1_" + str(existingid)
            fci_table = "fci_leg1_" + str(existingid)
            cursor.execute(sql)
        else:
            sql = "INSERT INTO optimised_table_leg1 (id, month, year, applicable,last_updated,type) VALUES ('" + random_id + "','" + month + "','" + year + "','" + applicable + "','" + scenario_type + "','" + formatted_datetime + "')";
            cursor.execute(sql)
        
        connection.commit()
        warehouse_drop_query = 'DROP TABLE IF EXISTS ' + warehouse_table;
        #print(warehouse_drop_query)
        cursor.execute(warehouse_drop_query)
        connection.commit()
        create_warehouse_query = ("CREATE TABLE " + warehouse_table + " (district VARCHAR(100) NOT NULL, name VARCHAR(100) NOT NULL, id VARCHAR(100) NOT NULL, warehousetype VARCHAR(100) NOT NULL, type VARCHAR(100) NOT NULL, latitude VARCHAR(100) NOT NULL, longitude VARCHAR(100) NOT NULL, storage VARCHAR(100) NOT NULL, uniqueid VARCHAR(100) NOT NULL, active VARCHAR(10) NOT NULL DEFAULT '1')")
        cursor.execute(create_warehouse_query)
        connection.commit()
        copy_warehouse_data = ("INSERT INTO " + warehouse_table + " SELECT * FROM warehouse WHERE active='1' AND warehousetype<>'fci'")
        cursor.execute(copy_warehouse_data)
        connection.commit()
        
        fci_drop_query = 'DROP TABLE IF EXISTS ' + fci_table;
        cursor.execute(fci_drop_query)
        create_fci_query = ("CREATE TABLE " + fci_table + " (district VARCHAR(100) NOT NULL, name VARCHAR(100) NOT NULL, id VARCHAR(100) NOT NULL, warehousetype VARCHAR(100) NOT NULL, type VARCHAR(100) NOT NULL, latitude VARCHAR(100) NOT NULL, longitude VARCHAR(100) NOT NULL, storage VARCHAR(100) NOT NULL, uniqueid VARCHAR(100) NOT NULL, active VARCHAR(10) NOT NULL DEFAULT '1')")
        cursor.execute(create_fci_query)
        connection.commit()
        copy_fci_data = ("INSERT INTO " + fci_table + " SELECT * FROM warehouse WHERE active='1' AND warehousetype='fci'")
        cursor.execute(copy_fci_data)
        connection.commit()
        
        excel_file_path = 'Backend//Result_Sheet_leg1.xlsx'
        
        columns_to_fetch = ['Scenario','From','From_State','From_ID','From_Name','From_District','From_Lat','From_Long','To','To_State','To_ID','To_Name', 'To_District', 'To_Lat', 'To_Long','commodity','quantity','Distance']
        df = pd.read_excel(excel_file_path)
        selected_data = df[columns_to_fetch]
        sql = 'DROP TABLE IF EXISTS ' + table_name;
        cursor.execute(sql)
        connection.commit()
        
        sql = "CREATE TABLE " + table_name + " ( scenario VARCHAR(150) NOT NULL, `from` VARCHAR(150) NOT NULL,from_state VARCHAR(150) NOT NULL, from_id VARCHAR(150) NOT NULL, from_name VARCHAR(150) NOT NULL, from_district VARCHAR(150) NOT NULL, from_lat VARCHAR(150) NOT NULL,from_long VARCHAR(150) NOT NULL, `to` VARCHAR(150) NOT NULL,to_state VARCHAR(150) NOT NULL,to_id VARCHAR(150) NOT NULL, to_name VARCHAR(150) NOT NULL, to_district VARCHAR(150) NOT NULL, to_lat VARCHAR(150) NOT NULL, to_long VARCHAR(150) NOT NULL, commodity VARCHAR(150) NOT NULL,quantity VARCHAR(150) NOT NULL, distance VARCHAR(150) NOT NULL, approve_admin VARCHAR(100) , approve_district VARCHAR(100) , new_id_admin VARCHAR(100), new_id_district VARCHAR(100) , new_name_admin VARCHAR(100) , new_name_district VARCHAR(10) , reason_admin VARCHAR(255) , reason_district VARCHAR(255), new_distance_admin VARCHAR(100), new_distance_district VARCHAR(100), district_change_approve VARCHAR(100), status VARCHAR(100) )";
        cursor.execute(sql)
        connection.commit()
        
        for (index, row) in selected_data.iterrows():
            sql = 'INSERT INTO ' + table_name + ' (`scenario`, `from`, `from_state`, `from_id`, `from_name`, `from_district`, `from_lat`, `from_long`, `to`, `to_state`, `to_id`, `to_name`, `to_district`, `to_lat`, `to_long`, `commodity`, `quantity`, `distance`) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)'
            values = tuple(row)
            cursor.execute(sql, values)
            connection.commit()
 
    if connection.is_connected():
        cursor.close()
        connection.close()
    return jsonify({'status': 1})




#@app.route('/saveMonthlyData', methods=['POST'])
def save_monthly_data(month, year, data):
    connection = connect_to_database()
    table_name = "optimised_table"
    
    try:
        if connection.is_connected():
            cursor = connection.cursor()

            # Check if data for the given year and month already exists
            sql_check = "SELECT id FROM " + table_name + " WHERE year = %s AND month = %s"
            cursor.execute(sql_check, (year, month))
            existing_data = cursor.fetchone()

            if existing_data:
                # Update existing data
                sql_update = "UPDATE " + table_name + " SET data = %s WHERE id = %s"
                values_update = (data, existing_data[0])
                cursor.execute(sql_update, values_update)
            else:
                # Insert new data
                random_id = str(uuid.uuid4())
                sql_insert = "INSERT INTO " + table_name + " (month, year, data, id) VALUES (%s, %s, %s, %s)"
                values_insert = (month, year, data, random_id)
                cursor.execute(sql_insert, values_insert)
            connection.commit()
    except mysql.connector.Error as err:
        # Handle the error, print or log it
        print(f"Error: {err}")
        return jsonify({'status': 0, 'error': str(err)})
    finally:
        if connection.is_connected():
            cursor.close()
            connection.close()
    
    return jsonify({'status': 1})


def save_monthly_data_leg1(month, year, data):
    connection = connect_to_database()
    table_name = "optimised_table_leg1"
    
    try:
        if connection.is_connected():
            cursor = connection.cursor()

            # Check if data for the given year and month already exists
            sql_check = "SELECT id FROM " + table_name + " WHERE year = %s AND month = %s"
            cursor.execute(sql_check, (year, month))
            existing_data = cursor.fetchone()

            if existing_data:
                # Update existing data
                sql_update = "UPDATE " + table_name + " SET data = %s WHERE id = %s"
                values_update = (data, existing_data[0])
                cursor.execute(sql_update, values_update)
            else:
                # Insert new data
                random_id = str(uuid.uuid4())
                sql_insert = "INSERT INTO " + table_name + " (month, year, data, id) VALUES (%s, %s, %s, %s)"
                values_insert = (month, year, data, random_id)
                cursor.execute(sql_insert, values_insert)
            connection.commit()
    except mysql.connector.Error as err:
        # Handle the error, print or log it
        print(f"Error: {err}")
        return jsonify({'status': 0, 'error': str(err)})
    finally:
        if connection.is_connected():
            cursor.close()
            connection.close()
    
    return jsonify({'status': 1})


@app.route('/readMonthlyData', methods=['POST'])
def get_monthly_data():
    try:
        connection = connect_to_database()
        table_name = "optimised_table"

        if connection.is_connected():
            cursor = connection.cursor()

            # Retrieve all data from the monthlydata table
            sql_select_all = "SELECT year, month, data FROM " + table_name
            cursor.execute(sql_select_all)
            data_rows = cursor.fetchall()

            # Convert data to a list of dictionaries
            columns = [column[0] for column in cursor.description]
            result = [dict(zip(columns, row)) for row in data_rows]

    except mysql.connector.Error as err:
        # Handle the error, print or log it
        print(f"Error: {err}")
        return jsonify({'status': 0, 'error': str(err)})
    finally:
        if connection.is_connected():
            cursor.close()
            connection.close()

    return jsonify({'status': 1, 'data': result})
   
@app.route('/processCancel', methods=['POST'])
def processCancel():
    global stop_process
    stop_process = True
    job_id = request.form.get("job_id")
    client_id = request.form.get("client_id")
    if job_id:
        _job_update(job_id, status="cancelled", message="process stopped by user")
    elif client_id:
        job = _job_get_active_for_client(client_id)
        if job:
            _job_update(job["job_id"], status="cancelled", message="process stopped by user")
            
    data = {}
    data['status'] = 0
    data['message'] = "process stopped"
    json_data = json.dumps(data)
    json_object = json.loads(json_data)
    return json.dumps(json_object, indent=1)

@app.route('/processFile', methods=['POST'])
def processFile():
    global stop_process
    stop_process = False

    if request.form.get("async") == "1":
        client_id = request.form.get("client_id") or request.form.get("username") or request.form.get("user") or ""
        if not client_id:
            client_id = "anonymous"
        form_dict = request.form.to_dict(flat=True)
        job_id = _job_create(client_id, endpoint="/processFile", message="queued", payload=json.dumps(form_dict))
        py_exe = sys.executable or "python"
        if getattr(sys, 'frozen', False):
            subprocess.Popen([py_exe, "--run-job", job_id, SERVER_INSTANCE_ID], close_fds=True)
        else:
            script_path = os.path.abspath(__file__)
            subprocess.Popen([py_exe, script_path, "--run-job", job_id, SERVER_INSTANCE_ID], close_fds=True)
        return jsonify({"status": 1, "job_id": job_id, "message": "processing started"})
    json_data = request.form
    write_log("User -> " + " Optimization Start Requested JSON -> " + str(json_data))
    scenario_type = request.form.get('type')
    if scenario_type == "intra":
        message = 'DataFile file is incorrect'
        try:
            USN = pd.ExcelFile('Backend//Data_1.xlsx')
            month = request.form.get('month')        
            year = request.form.get('year')
            applicable = request.form.get('applicable')
        except Exception as e:
            data = {}
            data['status'] = 0
            data['message'] = message
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)
        input = pd.ExcelFile('Backend//Data_1.xlsx')
        node1 = pd.read_excel(input,sheet_name="A.1 Warehouse")
        node2 = pd.read_excel(input,sheet_name="A.2 FPS")

        node1 = pd.read_excel(input,sheet_name="A.1 Warehouse")
        node2 = pd.read_excel(input,sheet_name="A.2 FPS")
        dist = [[0 for a in range(len(node2["FPS_ID"]))] for b in range(len(node1["WH_ID"]))]
        phi_1 = []
        phi_2 = []
        delta_phi = []
        delta_lambda = []
        R = 6371 

        for i in node1.index:
            for j in node2.index:
                phi_1=math.radians(node1["WH_Lat"][i])
                phi_2=math.radians(node2["FPS_Lat"][j])
                delta_phi=math.radians(node2["FPS_Lat"][j]-node1["WH_Lat"][i])
                delta_lambda=math.radians(node2["FPS_Long"][j]-node1["WH_Long"][i])
                x=math.sin(delta_phi / 2.0) ** 2 + math.cos(phi_1) * math.cos(phi_2) * math.sin(delta_lambda / 2.0) ** 2
                y=2 * math.atan2(math.sqrt(x), math.sqrt(1 - x))
                dist[i][j]=R*y
                
        
        FCI = pd.read_excel(USN, sheet_name='A.1 Warehouse', index_col=None)
        FPS = pd.read_excel(USN, sheet_name='A.2 FPS', index_col=None)

        FCI['WH_District'] = FCI['WH_District'].apply(lambda x: x.replace(' ', ''))
        FPS['FPS_District'] = FPS['FPS_District'].apply(lambda x: x.replace(' ', ''))
        #print(FCI)
        
        excel_path = "Backend//Distance_Initial_L2.xlsx"
        output_path = "Backend//Distance_Initial_L2_updated.xlsx"
        sheet_name = "BG_BG"
        excel_password = "distf"

        # ---------- Step 1: Get latest optimisation table ---------- #
        try:
            conn = connect_to_database()
            cursor = conn.cursor(dictionary=True)

            cursor.execute("""
                SELECT id
                FROM optimised_table
                WHERE month = %s and year = %s
                ORDER BY last_updated DESC
                LIMIT 1
            """, (month, year))
            opt = cursor.fetchone()
            updates = []
            if opt:
                table_name = f"optimiseddata_{opt['id']}"
                cursor.execute("SHOW TABLES LIKE %s", (table_name,))
                table_exists = cursor.fetchone()
                if table_exists:
                    cursor.execute(f"""
                        SELECT from_id, to_id, new_distance_district, approve_district
                        FROM `{table_name}`
                        WHERE LOWER(approve_district) = 'no'
                    """)
                    updates = cursor.fetchall()
            cursor.close()
            conn.close()

            if updates:
                # ---------- Step 2: Decrypt Excel ---------- #
                decrypted = io.BytesIO()
                with open(excel_path, "rb") as f:
                    office = msoffcrypto.OfficeFile(f)
                    office.load_key(password=excel_password)
                    office.decrypt(decrypted)

                decrypted.seek(0)

                # ---------- Step 3: Read Excel and Parse All Sheets ---------- #
                xl = pd.ExcelFile(decrypted, engine="openpyxl")
                sheets = {name: xl.parse(name) for name in xl.sheet_names}
                df = sheets[sheet_name]

                df.rename(columns={df.columns[0]: "to_id"}, inplace=True)
                df["to_id"] = df["to_id"].astype(str)
                df.set_index("to_id", inplace=True)

                df.columns = df.columns.astype(str)

                # ---------- Step 4: Intelligent Update + Minimal Append ---------- #
                updated_cells = 0
                appended_routes = 0

                for row in updates:
                    from_id = str(row["from_id"])
                    to_id = str(row["to_id"])
                    new_dist = row.get("new_distance_district")
                    if new_dist is not None:
                        try:
                            distance = float(new_dist)
                            if distance > 0:
                                # ---- Ensure ROW exists ---- #
                                if to_id not in df.index:
                                    df.loc[to_id] = 0
                                    appended_routes += 1

                                # ---- Ensure COLUMN exists ---- #
                                if from_id not in df.columns:
                                    df[from_id] = 0
                                    appended_routes += 1

                                # ---- Update the specific cell ---- #
                                if df.at[to_id, from_id] != distance:
                                    df.at[to_id, from_id] = distance
                                    updated_cells += 1
                        except (ValueError, TypeError):
                            pass

                # ---------- Step 5: Save Excel with All Sheets and Encrypt ---------- #
                output_path = "Backend//Distance_Initial_L2_updated.xlsx"
                sheets[sheet_name] = df.reset_index()

                plain_buf = io.BytesIO()
                with pd.ExcelWriter(plain_buf, engine="xlsxwriter") as writer:
                    for name, sheet_df in sheets.items():
                        sheet_df.to_excel(writer, sheet_name=name, index=False)
                plain_buf.seek(0)

                file = msoffcrypto.format.ooxml.OOXMLFile(plain_buf)
                with open(output_path, "wb") as f_out:
                    file.encrypt(excel_password, f_out)
            else:
                import shutil
                shutil.copy(excel_path, output_path)
        except Exception as e:
            write_log("Error updating distance matrix: " + str(e))


       
        
    
        # ================= READ INPUT =================
        input_file = 'Backend//Data_1.xlsx'
        input1 = pd.ExcelFile(input_file)

        FCI = pd.read_excel(input1, sheet_name='A.1 Warehouse')
        FPS = pd.read_excel(input1, sheet_name='A.2 FPS')
        
       

        # ================= CHECK CONDITION =================
        total_demand = (FPS['Allocation_Wheat'].sum() +FPS['Allocation_FRice'].sum() )
        if total_demand > 0:
        
            # ================= CLEAN DISTRICTS =================
            FCI['WH_District'] = FCI['WH_District'].astype(str).str.replace(' ', '').str.lower()
            FPS['FPS_District'] = FPS['FPS_District'].astype(str).str.replace(' ', '').str.lower()

            # ================= FIND COMMON DISTRICTS =================
            districts = list(set(FCI['WH_District']).intersection(set(FPS['FPS_District'])))

            columns_18 = [
                'Scenario','From','From_State','From_District','From_ID','From_Name','From_Lat','From_Long',
                'To','To_ID','To_Name','To_State','To_District','To_Lat','To_Long','commodity','quantity'
            ]

            # =========================================================
            # ================= DISTRICT OPT ===========================
            # =========================================================
            if len(districts) > 0:

                final_df = pd.DataFrame()

                for dist_name in districts:
                     
                    print(f"Running for district: {dist_name}")
 

                    FCI_d = FCI[FCI['WH_District'] == dist_name].reset_index(drop=True)
                    FPS_d = FPS[FPS['FPS_District'] == dist_name].reset_index(drop=True)
                    

                    if len(FCI_d) == 0 or len(FPS_d) == 0:
                        continue

                    # DISTANCE
                    R = 6371
                    dist = [[0]*len(FPS_d) for _ in range(len(FCI_d))]

                    for i in FCI_d.index:
                        for j in FPS_d.index:
                            phi_1 = math.radians(FCI_d["WH_Lat"][i])
                            phi_2 = math.radians(FPS_d["FPS_Lat"][j])

                            dphi = math.radians(FPS_d["FPS_Lat"][j] - FCI_d["WH_Lat"][i])
                            dlambda = math.radians(FPS_d["FPS_Long"][j] - FCI_d["WH_Long"][i])

                            x = math.sin(dphi/2)**2 + math.cos(phi_1)*math.cos(phi_2)*math.sin(dlambda/2)**2
                            dist[i][j] = 2 * 6371 * math.atan2(math.sqrt(x), math.sqrt(1-x))

                    # MODEL
                    model = LpProblem(f'Supply_{dist_name}', LpMinimize)

                    Allocation = LpVariable.matrix(
                        'X',
                        [(i,j) for i in range(len(FCI_d)) for j in range(len(FPS_d))],
                        lowBound=0
                    )
                    Allocation = np.array(Allocation).reshape(len(FCI_d), len(FPS_d))

                    Binary = LpVariable.matrix(
                        'Y',
                        [(i,j) for i in range(len(FCI_d)) for j in range(len(FPS_d))],
                        cat='Binary'
                    )
                    Binary = np.array(Binary).reshape(len(FCI_d), len(FPS_d))

                    # CONSTRAINTS
                    for i in range(len(FCI_d)):
                        for j in range(len(FPS_d)):
                            model += Allocation[i][j] <= 1000000 * Binary[i][j]

                    for j in range(len(FPS_d)):
                        model += lpSum(Binary[i][j] for i in range(len(FCI_d))) <= 1

                    model += lpSum(Allocation[i][j] * dist[i][j]
                                   for i in range(len(FCI_d))
                                   for j in range(len(FPS_d)))
                                   
                    FPS_d['Demand'] = FPS_d['Allocation_Wheat'] + FPS_d['Allocation_FRice'] 
								 			   

                    for j in range(len(FPS_d)):
                        model += lpSum(Allocation[i][j] for i in range(len(FCI_d))) == FPS_d['Demand'][j]

                    for i in range(len(FCI_d)):
                        model += lpSum(Allocation[i][j] for j in range(len(FPS_d))) <= FCI_d['Storage_Capacity'][i]

                    

                    model.solve(COIN_CMD(path=get_cbc_path(),msg=0,gapRel=0.03,timeLimit=120))
                    
                    

                    status = LpStatus[model.status]
                    if status not in ["Optimal", "Feasible"]:
                        print(f"Skipped {dist_name} - Status: {status}")
                        continue

                    # ================= EXTRACT RESULT =================
                    rows = []

                    for i in range(len(FCI_d)):
                        for j in range(len(FPS_d)):
                            val = Allocation[i][j].value()
                            if val and val > 0:
                                rows.append({
                                    
                                    'WH_ID': FCI_d['WH_ID'][i],
                                    'WH_D': dist_name,
                                    'FPS_ID': FPS_d['FPS_ID'][j],
                                    'FPS_D': dist_name,
                                    'Values': val
                                })

                    df_temp = pd.DataFrame(rows)

                    final_df = pd.concat([final_df, df_temp], ignore_index=True)


                # ================= SAVE OUTPUT =================
                final_df.to_excel('Backend//Tagging_Sheet_Pre.xlsx', index=False)

                print("[OK] LP District-wise tagging completed") 
                
                
                        # ================= POST PROCESSING =================

                # --- 1. WAREHOUSE USED ALLOCATION ---
                # Sum allocation per warehouse
                wh_alloc = final_df.groupby('WH_ID')['Values'].sum().reset_index()
                wh_alloc.rename(columns={'Values': 'Used_Allocation'}, inplace=True)
                

                # Merge with original warehouse data
                FCI_updated = pd.merge(FCI, wh_alloc, on='WH_ID', how='left')
                
               

                # Fill NaN with 0 (warehouses not used)
                FCI_updated['Used_Allocation'] = FCI_updated['Used_Allocation'].fillna(0)
                FCI_updated['Remaining_Capacity'] = (FCI_updated['Storage_Capacity'] - FCI_updated['Used_Allocation'])
                FCI_updated = FCI_updated[FCI_updated['Remaining_Capacity'] >= 1000]
                
                


                        # --- 2. FPS NOT COVERED ---
                # FPS that actually received allocation
                covered_fps = final_df['FPS_ID'].unique()

                # Filter FPS not covered in solution
                FPS_not_covered = FPS[~FPS['FPS_ID'].isin(covered_fps)].copy()
                
                


                # ================= SAVE TO NEW FILE =================
                output_file = 'Backend//Data_3.xlsx'

                with pd.ExcelWriter(output_file, engine='xlsxwriter') as writer:
                    FCI_updated.to_excel(writer, sheet_name='A.1 Warehouse', index=False)
                    FPS_not_covered.to_excel(writer, sheet_name='A.2 FPS', index=False)
                    

                df31 = pd.read_excel('Backend//Tagging_Sheet_Pre.xlsx')
                USN = pd.ExcelFile('Backend//Data_1.xlsx')
                FCI = pd.read_excel(USN, sheet_name='A.1 Warehouse', index_col=None)
                FPS = pd.read_excel(USN, sheet_name='A.2 FPS', index_col=None)
				
                df4 = pd.merge(df31, FCI, on='WH_ID', how='inner')
                df4 = df4[[
					'WH_ID',
					'WH_Name',
					'WH_District',
					'WH_Lat',
					'WH_Long',
					'FPS_ID',
					'Values',
					]]
                    
                df4 = pd.merge(df4, FPS, on='FPS_ID', how='inner')
				
                df51 = df4[[
					'WH_ID',
					'WH_Name',
					'WH_District',
					'WH_Lat',
					'WH_Long',
					'FPS_ID',
					'FPS_Name',
					'FPS_District',
					'FPS_Lat',
					'FPS_Long',
					'Allocation_Wheat',
				]]
                
                df51.insert(0, 'Scenario', 'Optimized')
                df51.insert(1, 'From', 'TPDS')
                df51.insert(2, 'From_State', 'Bihar')
                df51.insert(7, 'To', 'FPS')
                df51.insert(8, 'To_State', 'Bihar')
                df51.insert(9, 'commodity', 'Wheat')
                
                df51.rename(columns={
					'WH_ID': 'From_ID',
					'WH_Name': 'From_Name',
					'WH_Lat': 'From_Lat',
					'WH_Long': 'From_Long',
				}, inplace=True)
                
                df51.rename(columns={
					'FPS_ID': 'To_ID',
					'FPS_Name': 'To_Name',
					'FPS_Lat': 'To_Lat',
					'FPS_Long': 'To_Long',
					'Allocation_Wheat': 'quantity',
				}, inplace=True)
                
                df51.rename(columns={'WH_District': 'From_District',
                'FPS_District': 'To_District'}, inplace=True)
                df51 = df51.loc[:, [
					'Scenario',
					'From',
					'From_State',
					'From_District',
					'From_ID',
					'From_Name',
					'From_Lat',
					'From_Long',
					'To',
					'To_ID',
					'To_Name',
					'To_State',
					'To_District',
					'To_Lat',
					'To_Long',
					'commodity',
					'quantity',
					]]
                
                df41 = pd.merge(df31, FCI, on='WH_ID', how='inner')
                df41 = df41[[
					'WH_ID',
					'WH_Name',
					'WH_District',
					'WH_Lat',
					'WH_Long',
					'FPS_ID',
					'Values',
					]]
                
                df41 = pd.merge(df41, FPS, on='FPS_ID', how='inner')
                df511 = df41[[
					'WH_ID',
					'WH_Name',
					'WH_District',
					'WH_Lat',
					'WH_Long',
					'FPS_ID',
					'FPS_Name',
					'FPS_District',
					'FPS_Lat',
					'FPS_Long',
					'Allocation_FRice',
					]]
                df511.insert(0, 'Scenario', 'Optimized')
                df511.insert(1, 'From', 'TPDS')
                df511.insert(2, 'From_State', 'Bihar')
                df511.insert(7, 'To', 'FPS')
                df511.insert(8, 'To_State', 'Bihar')
                df511.insert(9, 'commodity', 'FRice')
                
                df511.rename(columns={
					'WH_ID': 'From_ID',
					'WH_Name': 'From_Name',
					'WH_Lat': 'From_Lat',
					'WH_Long': 'From_Long',
					}, inplace=True)
                df511.rename(columns={
					'FPS_ID': 'To_ID',
					'FPS_Name': 'To_Name',
					'FPS_Lat': 'To_Lat',
					'FPS_Long': 'To_Long',
					'Allocation_FRice': 'quantity',
					
					}, inplace=True)
                df511.rename(columns={'WH_District': 'From_District',
						   'FPS_District': 'To_District'}, inplace=True)   
                df511 = df511.loc[:, [
					'Scenario',
					'From',
					'From_State',
					'From_District',
					'From_ID',
					'From_Name',
					'From_Lat',
					'From_Long',
					'To',
					'To_ID',
					'To_Name',
					'To_State',
					'To_District',
					'To_Lat',
					'To_Long',
					'commodity',
					'quantity',
					]]
                
                
                
                def convert_to_numeric(value):
                    try:
                        return pd.to_numeric(value)
                    except ValueError:
                        return value
                        
                
                df51['From_ID'] = df51['From_ID'].apply(convert_to_numeric)
                df51['To_ID'] = df51['To_ID'].apply(convert_to_numeric)
                
                df_combined = pd.concat([df51, df511])
                df_combined1 = df_combined[df_combined['quantity'] != 0]
                df_combined1['From_ID'] = df_combined1['From_ID'].apply(convert_to_numeric)
                df_combined1['To_ID'] = df_combined1['To_ID'].apply(convert_to_numeric)
						
               
                df_combined1.to_excel('Backend//Tagging_Sheet_Pre12.xlsx', sheet_name='BG_FPS',index=False,)


            else:
                pd.DataFrame(columns=columns_18).to_excel('Backend//Tagging_Sheet_Pre12.xlsx', index=False)

            # ================= SECOND STAGE =================
            
            
            input_file = 'Backend//Data_3.xlsx'
            input1 = pd.ExcelFile(input_file)

            FCI = pd.read_excel(input1, sheet_name='A.1 Warehouse')
            FPS = pd.read_excel(input1, sheet_name='A.2 FPS')
            
            total_demand1 = (FPS['Allocation_Wheat'].sum() +FPS['Allocation_FRice'].sum())

            if total_demand1 > 0:

                node1 = FCI.copy()
                node2 = FPS.copy()

                # ================= DISTANCE MATRIX =================
                dist = [[0 for _ in range(len(node2))] for _ in range(len(node1))]
                R = 6371

                for i in node1.index:
                    for j in node2.index:
                        phi_1 = math.radians(node1["WH_Lat"][i])
                        phi_2 = math.radians(node2["FPS_Lat"][j])
                        delta_phi = math.radians(node2["FPS_Lat"][j] - node1["WH_Lat"][i])
                        delta_lambda = math.radians(node2["FPS_Long"][j] - node1["WH_Long"][i])

                        x = math.sin(delta_phi / 2.0) ** 2 + math.cos(phi_1) * math.cos(phi_2) * math.sin(delta_lambda / 2.0) ** 2
                        y = 2 * math.atan2(math.sqrt(x), math.sqrt(1 - x))
                        dist[i][j] = R * y

                # ================= CLEAN DATA =================
                FCI['WH_District'] = FCI['WH_District'].str.replace(' ', '')
                FPS['FPS_District'] = FPS['FPS_District'].str.replace(' ', '')
                
                

                # ================= MODEL =================
                model = LpProblem('Supply-Demand-Problem', LpMinimize)

                # ================= VARIABLES =================
                Variable1 = []
                for i in range(len(FCI)):
                    for j in range(len(FPS)):
                        Variable1.append(f"{FCI['WH_ID'][i]}_{FCI['WH_District'][i]}_{FPS['FPS_ID'][j]}_{FPS['FPS_District'][j]}_Wheat")

                DV_Variables1 = LpVariable.matrix('X', Variable1, lowBound=0)
                Allocation1 = np.array(DV_Variables1).reshape(len(FCI), len(FPS))

                Variable1I = []
                for i in range(len(FCI)):
                    for j in range(len(FPS)):
                        Variable1I.append(f"{FCI['WH_ID'][i]}_{FCI['WH_District'][i]}_{FPS['FPS_ID'][j]}_{FPS['FPS_District'][j]}_Wheat1")

                DV_Variables1I = LpVariable.matrix('Y', Variable1I, cat='Binary')
                Allocation1I = np.array(DV_Variables1I).reshape(len(FCI), len(FPS))

                # ================= CONSTRAINTS =================
                for i in range(len(FPS)):
                    model += lpSum(Allocation1I[k][i] for k in range(len(FCI))) <= 1
                    
                

                for i in range(len(FCI)):
                    for j in range(len(FPS)):
                        model += Allocation1[i][j] <= 1000000 * Allocation1I[i][j]
                        
                       

                # Objective
                model += lpSum(Allocation1[i][j] * dist[i][j] for i in range(len(FCI)) for j in range(len(FPS)))

                # Demand
                FPS['Demand_R'] = FPS['Allocation_Wheat'] + FPS['Allocation_FRice']
                for i in range(len(FPS)):
                    model += lpSum(Allocation1[j][i] for j in range(len(FCI))) == FPS['Demand_R'][i]
                    
               
                # Supply
                for i in range(len(FCI)):
                    model += lpSum(Allocation1[i][j] for j in range(len(FPS))) <= FCI['Remaining_Capacity'][i]
                    
                print("shall5")        

                # Distance constraint
                MAX_DIST = 200
                for i in range(len(FCI)):
                    for j in range(len(FPS)):
                        if dist[i][j] > MAX_DIST:
                            model += Allocation1[i][j] == 0
                            model += Allocation1I[i][j] == 0

                # ================= SOLVE =================
                print("opt_start")
                model.solve(COIN_CMD(path=get_cbc_path(),msg=0,gapRel=0.03,timeLimit=600))
                print("opt_end")
                
                status = LpStatus[model.status]
                print("Solver Status:", status)
                
                # [OK] Accept feasible solutions
                if status not in ["Optimal", "Feasible"]:
                    print("Optimization failed:", status)

                    data = {
                        "status": 0,
                        "message": f"Solver Status: {status}"
                    }

                    return json.dumps(data, indent=1)

                

                Output_File = open('Backend//Inter_District3.csv', 'w')
                for v in model.variables():
                    if v.value() > 0:
                        Output_File.write(v.name + '\t' + str(v.value()) + '\n')

                Output_File = open('Backend//Inter_District3.csv', 'w')
                for v in model.variables():
                    if v.value() > 0:
                        Output_File.write(v.name + '\t' + str(v.value()) + '\n')                          

                # ================= PROCESS OUTPUT =================
                df9 = pd.read_csv('Backend//Inter_District3.csv', header=None)
                df9.columns = ['Tagging']

                df9[['Var','WH_ID','W_D','FPS_ID','FPS_D','commodity_Value']] = df9['Tagging'].str.split('_', n=5, expand=True)
                df9[['commodity','Values']] = df9['commodity_Value'].str.split('\t', expand=True)
                
               

                df9 = df9[df9['commodity'] != 'Wheat1']
                
                def convert_to_numeric(value):
                    try:
                        return pd.to_numeric(value)
                    except ValueError:
                        return value
                

                df9['WH_ID'] = df9['WH_ID'].apply(convert_to_numeric)
                df9['FPS_ID'] = df9['FPS_ID'].apply(convert_to_numeric)
                
                df9.to_excel('Backend//Tagging_Sheet_Pre_Wheat.xlsx', sheet_name='BG_FPS')
                df32 = pd.read_excel('Backend//Tagging_Sheet_Pre_Wheat.xlsx')

                
                USN = pd.ExcelFile('Backend//Data_1.xlsx')
                FCI = pd.read_excel(USN, sheet_name='A.1 Warehouse', index_col=None)
                FPS = pd.read_excel(USN, sheet_name='A.2 FPS', index_col=None)

                # ================= MERGE =================
                df41 = pd.merge(df32, FCI, on='WH_ID')
                df41 = pd.merge(df41, FPS, on='FPS_ID')

                df511 = df41[[
                    'WH_ID','WH_Name','WH_District','WH_Lat','WH_Long',
                    'FPS_ID','FPS_Name','FPS_District','FPS_Lat','FPS_Long','Allocation_Wheat'
                ]]

                # Add columns
                df511.insert(0, 'Scenario', 'Optimized')
                df511.insert(1, 'From', 'TPDS')
                df511.insert(2, 'From_State', 'Bihar')
                df511.insert(7, 'To', 'FPS')
                df511.insert(8, 'To_State', 'Bihar')
                df511.insert(9, 'commodity', 'Wheat')

                # Rename
                df511.rename(columns={
                    'WH_ID':'From_ID','WH_Name':'From_Name','WH_Lat':'From_Lat','WH_Long':'From_Long',
                    'FPS_ID':'To_ID','FPS_Name':'To_Name','FPS_Lat':'To_Lat','FPS_Long':'To_Long',
                    'Allocation_Wheat':'quantity',
                    'WH_District':'From_District','FPS_District':'To_District'
                }, inplace=True)

                df511 = df511[[
                    'Scenario','From','From_State','From_District','From_ID','From_Name','From_Lat','From_Long',
                    'To','To_ID','To_Name','To_State','To_District','To_Lat','To_Long','commodity','quantity'
                ]]
				
				# ================= MERGE =================
                df411 = pd.merge(df32, FCI, on='WH_ID')
                df411 = pd.merge(df411, FPS, on='FPS_ID')

                df5111 = df411[[
                    'WH_ID','WH_Name','WH_District','WH_Lat','WH_Long',
                    'FPS_ID','FPS_Name','FPS_District','FPS_Lat','FPS_Long','Allocation_FRice'
                ]]

                # Add columns
                df5111.insert(0, 'Scenario', 'Optimized')
                df5111.insert(1, 'From', 'TPDS')
                df5111.insert(2, 'From_State', 'Bihar')
                df5111.insert(7, 'To', 'FPS')
                df5111.insert(8, 'To_State', 'Bihar')
                df5111.insert(9, 'commodity', 'FRice')

                # Rename
                df5111.rename(columns={
                    'WH_ID':'From_ID','WH_Name':'From_Name','WH_Lat':'From_Lat','WH_Long':'From_Long',
                    'FPS_ID':'To_ID','FPS_Name':'To_Name','FPS_Lat':'To_Lat','FPS_Long':'To_Long',
                    'Allocation_FRice':'quantity',
                    'WH_District':'From_District','FPS_District':'To_District'
                }, inplace=True)

                df5111 = df5111[[
                    'Scenario','From','From_State','From_District','From_ID','From_Name','From_Lat','From_Long',
                    'To','To_ID','To_Name','To_State','To_District','To_Lat','To_Long','commodity','quantity'
                ]]
				
				
                
                def convert_to_numeric(value):
                    try:
                        return pd.to_numeric(value)
                    except ValueError:
                        return value
                        
                df_combined2 = pd.concat([df511, df5111])
                df_combined21 = df_combined2[df_combined2['quantity'] != 0]
                df_combined21['From_ID'] = df_combined21['From_ID'].apply(convert_to_numeric)
                df_combined21['To_ID'] = df_combined21['To_ID'].apply(convert_to_numeric)

                # Save final
                df_combined21.to_excel('Backend/Tagging_Sheet_Pre13.xlsx', sheet_name='BG_FPS', index=False)


                

            else:
                pd.DataFrame(columns=columns_18).to_excel('Backend/Tagging_Sheet_Pre13.xlsx', index=False)

            # ================= FINAL MERGE =================
            df1 = pd.read_excel('Backend/Tagging_Sheet_Pre12.xlsx')
            df2 = pd.read_excel('Backend/Tagging_Sheet_Pre13.xlsx')

            df_combined = pd.concat([df1, df2], ignore_index=True)
            df_combined.to_excel('Backend/Tagging_Sheet_Pre11.xlsx', index=False,sheet_name='BG_FPS1')

        # =========================================================
        # ================= MAIN ELSE (NO DEMAND) ==================
        # =========================================================
        else:
            print("[WARNING] Allocation_Wheat sum is 0 -> skipping full run")

            columns_18 = [
                'Scenario','From','From_State','From_District','From_ID','From_Name','From_Lat','From_Long',
                'To','To_ID','To_Name','To_State','To_District','To_Lat','To_Long','commodity','quantity'
            ]

            empty_df = pd.DataFrame(columns=columns_18)
            empty_df.to_excel('Backend/Tagging_Sheet_Pre11.xlsx', index=False,sheet_name='BG_FPS1')

        
        if stop_process==True:
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)          
        
        
        # ================= Read Input File =================
        input_file = pd.ExcelFile('Backend/Data_1.xlsx')
        
        print("Shallu")

        node1 = pd.read_excel(input_file, sheet_name="A.1 Warehouse")
        node2 = pd.read_excel(input_file, sheet_name="A.2 FPS")
        
        updated_excel_path = 'Backend//Distance_Initial_L2_updated.xlsx'
        ref_excel_path = updated_excel_path if os.path.exists(updated_excel_path) else 'Backend//Distance_Initial_L2.xlsx'
        DistanceBing = read_protected_excel(ref_excel_path, 'distf', sheet_name='BG_BG')
        Warehouse = read_protected_excel(ref_excel_path, 'distf', sheet_name='Warehouse')
        FPS = read_protected_excel(ref_excel_path, 'distf', sheet_name='FPS')


        # ================= Standardize IDs =================
        node1['WH_ID'] = node1['WH_ID'].astype(str).str.strip()
        Warehouse['WH_ID'] = Warehouse['WH_ID'].astype(str).str.strip()
        print("Shallu")

        node2['FPS_ID'] = node2['FPS_ID'].astype(str).str.strip()
        FPS['FPS_ID'] = FPS['FPS_ID'].astype(str).str.strip()

        # ================= Warehouse Comparison =================
        node1['Lat_Long_Check'] = (
            node1['WH_Lat'].astype(float).round(3).map('{:.3f}'.format)
            + ',' +
            node1['WH_Long'].astype(float).round(3).map('{:.3f}'.format)
        )

        Warehouse['Lat_Long_Check'] = (
            Warehouse['Lat_Long']
            .astype(str)
            .str.replace(' ', '', regex=False)
            .str.split(',', expand=True)
            .astype(float)
            .round(3)
            .apply(lambda x: f"{x[0]:.3f},{x[1]:.3f}", axis=1)
        )

        War = pd.merge(
            node1[['WH_ID', 'Lat_Long_Check']],
            Warehouse[['WH_ID', 'Lat_Long_Check']],
            on='WH_ID',
            suffixes=('_src', '_dist'),
            how='inner'
        )

        df1_w = War[
            War['Lat_Long_Check_src'] != War['Lat_Long_Check_dist']
        ]

        Warehouse_ID = set(
            df1_w['WH_ID'].astype(str).str.strip()
        )

        print("Changed Warehouses:", len(Warehouse_ID))

        # ================= FPS Comparison =================
        node2['Lat_Long_Check'] = (
            node2['FPS_Lat'].astype(float).round(3).map('{:.3f}'.format)
            + ',' +
            node2['FPS_Long'].astype(float).round(3).map('{:.3f}'.format)
        )

        FPS['Lat_Long_Check'] = (
            FPS['Lat_Long']
            .astype(str)
            .str.replace(' ', '', regex=False)
            .str.split(',', expand=True)
            .astype(float)
            .round(3)
            .apply(lambda x: f"{x[0]:.3f},{x[1]:.3f}", axis=1)
        )

        FPS1 = pd.merge(
            node2[['FPS_ID', 'Lat_Long_Check']],
            FPS[['FPS_ID', 'Lat_Long_Check']],
            on='FPS_ID',
            suffixes=('_src', '_dist'),
            how='inner'
        )

        df1_f = FPS1[
            FPS1['Lat_Long_Check_src'] != FPS1['Lat_Long_Check_dist']
        ]

        FPS_ID = set(
            df1_f['FPS_ID'].astype(str).str.strip()
        )

        print("Changed FPS:", len(FPS_ID))

        # ================= Update Distance Matrix =================

        BG_BG = DistanceBing.copy()

        # Convert all column names to string
        BG_BG.columns = BG_BG.columns.astype(str).str.strip()

        # First column contains FPS IDs
        first_col = BG_BG.columns[0]

        # Convert first column to string
        BG_BG[first_col] = BG_BG[first_col].astype(str).str.strip()

        # ---------- Remove Changed FPS Columns ----------
        fps_cols_to_remove = [
            col for col in BG_BG.columns
            if str(col).strip() in FPS_ID
        ]

        print("FPS Columns Removed:", len(fps_cols_to_remove))

        BG_BG = BG_BG.drop(
            columns=fps_cols_to_remove,
            errors='ignore'
        )

        # ---------- Remove Changed FPS Rows ----------
        rows_before = len(BG_BG)

        BG_BG = BG_BG[
            ~BG_BG[first_col].isin(FPS_ID)
        ]

        rows_removed = rows_before - len(BG_BG)

        print("FPS Rows Removed:", rows_removed)

        # ---------- Remove Changed Warehouse Columns ----------
        warehouse_cols_to_remove = [
            col for col in BG_BG.columns
            if str(col).strip() in Warehouse_ID
        ]

        print("Warehouse Columns Removed:", len(warehouse_cols_to_remove))

        BG_BG = BG_BG.drop(
            columns=warehouse_cols_to_remove,
            errors='ignore'
        )

        # ================= Save =================
        with pd.ExcelWriter('Backend//Bihar_Distance_L2.xlsx') as writer:
            BG_BG.to_excel(
                writer,
                sheet_name='BG_BG',
                index=False
            )

        print("Distance matrix updated successfully.")
        print("Final Shape:", BG_BG.shape)

            

        Cost = pd.ExcelFile("Backend//Bihar_Distance_L2.xlsx")
        BG_BG = pd.read_excel(Cost,sheet_name="BG_BG")
        Cost.close()
        data1 = pd.ExcelFile("Backend//Tagging_Sheet_Pre11.xlsx")
        df5 = pd.read_excel(data1,sheet_name="BG_FPS1")
        data1.close()

        Distance_BG_BG = {}
        column_list_BG_BG = list(BG_BG.columns.astype(str))
        row_list_BG_BG = list(BG_BG.iloc[:, 0].astype(str))

        for ind in df5.index:
            from_code = df5['From_ID'][ind]
            to_code = df5['To_ID'][ind]
            from_code_str = str(from_code)
            to_code_str = str(to_code)
            
            if to_code_str in row_list_BG_BG and from_code_str in column_list_BG_BG:
                index_i = row_list_BG_BG.index(to_code_str)
                index_j = column_list_BG_BG.index(from_code_str)
                key = to_code_str + "_" + from_code_str
                Distance_BG_BG[key] = BG_BG.iloc[index_i, index_j] 
                
                
        df5["Tagging"] = df5['To_ID'].astype(str) + '_' + df5['From_ID'].astype(str)
        df5['Distance'] = df5['Tagging'].map(Distance_BG_BG)
        df5.fillna('shallu', inplace=True)
        df5.to_excel('Backend//Result_Sheet12.xlsx', sheet_name='Warehouse_FPS', index=False)        
        
        
        
# ----------------------------------------------------------------------------------------------------------------------------------------------
        # -------------------- LOAD DATA --------------------
        Result_Sheet1 = pd.ExcelFile("Backend//Result_Sheet12.xlsx")
        df6 = pd.read_excel(Result_Sheet1, sheet_name="Warehouse_FPS")
        
        Result_Sheet1.close()

        # -------------------- FILTER SHALLU --------------------
        df7 = df6.loc[df6['Distance'] == "shallu"].reset_index(drop=True)
        
                

        # Drop Tagging column (safe)
        df6.drop(columns=['Tagging'], errors='ignore', inplace=True)

        # Common column structure
        columns = [
            'Scenario','From','From_State','From_District','From_ID','From_Name',
            'From_Lat','From_Long','To','To_ID','To_Name','To_State','To_District',
            'To_Lat','To_Long','commodity','quantity','Distance'
        ]
        
        

        # ============================
        # [OK] CASE 1: NO "shallu"
        # ============================
        if df7.empty:
            print("No 'shallu' found -> skipping API")

            df10 = df6.copy()
            df10 = df10[columns]

        # ============================
        # [OK] CASE 2: PROCESS API
        # ============================
        else:
            print(f"'shallu' found -> processing {len(df7)} rows")
            print("anmol")

            # -------------------- API DETAILS --------------------
            auth_url = 'https://staging2.pmgatishakti.gov.in/DFPD/authenticate'
            distance_url = 'https://staging2.pmgatishakti.gov.in/PMGatishaktiApiService/dfpdapi/roaddistance'

            auth_payload = {
                "username": "DFPD_C",
                "password": "W9Vtb8WKkt3"
            }

            FILE_PATH = 'distanceIndent.json'

            # -------------------- GET TOKEN --------------------
            def get_token():
                try:
                    response = requests.post(auth_url, json=auth_payload, timeout=240)
                    if response.status_code == 200:
                        return response.json().get('token')
                    return None
                except requests.exceptions.RequestException as e:
                    print("Auth API Error:", e)
                    raise Exception("PMGatiShakti Authentication Service is currently unavailable. Please check your internet connection or try again later.")

            # -------------------- BATCH API --------------------
            def process_batch(df_batch, token):
                headers = {'Authorization': f'Bearer {token}'}

                data = {
                    "parameter": [{
                        "src_lng": row["From_Long"],
                        "src_lat": row["From_Lat"],
                        "dest_lng": row["To_Long"],
                        "dest_lat": row["To_Lat"]
                    } for _, row in df_batch.iterrows()]
                }

                try:
                    with open(FILE_PATH, 'w') as f:
                        json.dump(data, f, indent=4)

                    with open(FILE_PATH, 'rb') as f:
                        files = {'LatsLongsFile': f}
                        response = requests.post(distance_url, headers=headers, files=files, timeout=240)

                    return response

                except requests.exceptions.RequestException as e:
                    print("Batch API Error:", e)
                    raise Exception("PMGatiShakti Distance Service is currently unavailable. Please check your internet connection or try again later.")
                except Exception as e:
                    print("Batch API Error:", e)
                    return None

            # -------------------- SINGLE ROW API --------------------
            def process_single_row(row, token):
                headers = {'Authorization': f'Bearer {token}'}

                data = {
                    "parameter": [{
                        "src_lng": row["From_Long"],
                        "src_lat": row["From_Lat"],
                        "dest_lng": row["To_Long"],
                        "dest_lat": row["To_Lat"]
                    }]
                }

                try:
                    with open(FILE_PATH, 'w') as f:
                        json.dump(data, f, indent=4)

                    with open(FILE_PATH, 'rb') as f:
                        files = {'LatsLongsFile': f}
                        response = requests.post(distance_url, headers=headers, files=files, timeout=240)

                    if response.status_code != 200:
                        return 0

                    res_json = response.json()
                    api_data = res_json.get("data", [])

                    if len(api_data) == 0:
                        return 0

                    distance = api_data[0].get("distance")

                    if isinstance(distance, (int, float)):
                        return distance

                    return 0

                except requests.exceptions.RequestException as e:
                    print("Row API Error:", e)
                    raise Exception("PMGatiShakti Distance Service is currently unavailable. Please check your internet connection or try again later.")
                except Exception as e:
                    print("Row API Error:", e)
                    return 0

            # -------------------- MAIN PROCESS --------------------
            batch_size = 1000
            total_rows = len(df7)
            num_batches = (total_rows + batch_size - 1) // batch_size

            dist3 = []

            for batch_num in range(num_batches):
                print(f"Processing batch {batch_num+1}/{num_batches}")

                start_idx = batch_num * batch_size
                end_idx = min((batch_num + 1) * batch_size, total_rows)
                df_batch = df7.iloc[start_idx:end_idx]

                token = get_token()
                if not token:
                    print("Token failed -> filling with 0")
                    dist3.extend([0] * len(df_batch))
                    continue

                response = process_batch(df_batch, token)

                fallback_required = False

                if not response or response.status_code != 200:
                    fallback_required = True
                else:
                    try:
                        response_json = response.json()
                        api_data = response_json.get("data", [])

                        if len(api_data) != len(df_batch):
                            fallback_required = True
                        else:
                            for row_data in api_data:
                                if not isinstance(row_data.get("distance"), (int, float)):
                                    fallback_required = True
                                    break
                    except:
                        fallback_required = True

                # ---------------- FALLBACK ----------------
                if fallback_required:
                    print(f"Batch {batch_num+1} failed -> row-wise fallback")

                    for _, row in df_batch.iterrows():
                        distance = process_single_row(row, token)

                        if distance == 0:
                            print(f"0 distance -> {row['From_ID']} to {row['To_ID']}")

                        dist3.append(distance)

                # ---------------- NORMAL ----------------
                else:
                    for row_data in api_data:
                        dist3.append(row_data.get("distance"))


            df7["Distance"] = dist3
            print("Pawaa")

            # Merge with non-shallu
            df9 = df6.loc[df6['Distance'] != "shallu"]

            df9 = df9[columns]
            df7 = df7[columns]

            df10 = pd.concat([df9, df7], ignore_index=True)

        # ============================
        # FINAL OUTPUT (COMMON)
        # ============================
        result = (df10['quantity'] * df10['Distance']).sum()

        print("Total Result:", result)

        df10.to_excel('Backend//Result_Sheet.xlsx', sheet_name='Warehouse_FPS', index=False)

        print("Process Completed Successfully")
# ----------------------------------------------------------------------------------------------------------------------------------------------        
              
    
        
# ----------------------------------------------------------------------------------------------------------------------------------------------        
        data ={}
        
        data["Scenario"]="Intra"
        data["Scenario_Baseline"] = "Baseline"
        
        data["WH_Used"] = df5['From_ID'].nunique()
        data["WH_Used_Baseline"] = "448"
        
        data["FPS_Used"] = df5['To_ID'].nunique()
        data["FPS_Used_Baseline"] = "51,037"
        
        
        
        data['Demand'] = df10["quantity"].astype(float).sum()
        data['Demand_Baseline'] = "45,97,144.9"
        
        data['Total_QKM'] = float(result)
        data['Total_QKM_Baseline'] = "4,42,73,101.58"
        
        Total_Demand=df10["quantity"].astype(float).sum()
        
        data['Average_Distance'] = float(round(result, 2)) / Total_Demand
        data['Average_Distance_Baseline'] = "9.63"

        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)                     

        save_to_database(month, year, applicable)
        save_monthly_data(month, year, float(result))
        
        def delete_files(file_paths):
            for file_path in file_paths:
                try:
                    if os.path.exists(file_path):  # Check if the file exists
                        os.remove(file_path)  # Delete the file
                        #print(f"{file_path} has been deleted.")
                    else:
                        print(f"{file_path} does not exist.")
                except Exception as e:
                    print(f"Error deleting {file_path}: {e}")

        # List of files to delete
        files_to_delete = [
            'Backend/Bihar_Distance_L2.xlsx',
            'Backend/Result_Sheet12.xlsx',
            'Backend//Tagging_Sheet_Pre11.xlsx',
            
        ]

        # Call the function to delete the files
        delete_files(files_to_delete)
        

        json_data = json.dumps(data)
        json_object = json.loads(json_data)

        if os.path.exists('ouputPickle.pkl'):
            os.remove('ouputPickle.pkl')

        # open pickle file
        dbfile1 = open('ouputPickle.pkl', 'ab')
        
    else:
        message = 'DataFile file is incorrect'
        try:
            USN = pd.ExcelFile('Backend//Data_1.xlsx')
            month = request.form.get('month')        
            year = request.form.get('year')
            applicable = request.form.get('applicable')
        except Exception as e:
            data = {}
            data['status'] = 0
            data['message'] = message
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)
        input = pd.ExcelFile('Backend//Data_1.xlsx')
        node1 = pd.read_excel(input,sheet_name="A.1 Warehouse")
        node2 = pd.read_excel(input,sheet_name="A.2 FPS")

        node1 = pd.read_excel(input,sheet_name="A.1 Warehouse")
        node2 = pd.read_excel(input,sheet_name="A.2 FPS")
        dist = [[0 for a in range(len(node2["FPS_ID"]))] for b in range(len(node1["WH_ID"]))]
        phi_1 = []
        phi_2 = []
        delta_phi = []
        delta_lambda = []
        R = 6371 

        for i in node1.index:
            for j in node2.index:
                phi_1=math.radians(node1["WH_Lat"][i])
                phi_2=math.radians(node2["FPS_Lat"][j])
                delta_phi=math.radians(node2["FPS_Lat"][j]-node1["WH_Lat"][i])
                delta_lambda=math.radians(node2["FPS_Long"][j]-node1["WH_Long"][i])
                x=math.sin(delta_phi / 2.0) ** 2 + math.cos(phi_1) * math.cos(phi_2) * math.sin(delta_lambda / 2.0) ** 2
                y=2 * math.atan2(math.sqrt(x), math.sqrt(1 - x))
                dist[i][j]=R*y
                
        
        FCI = pd.read_excel(USN, sheet_name='A.1 Warehouse', index_col=None)
        FPS = pd.read_excel(USN, sheet_name='A.2 FPS', index_col=None)

        if 'Allocation_FRice' in FPS.columns and 'Allocation_Rice' not in FPS.columns:
            FPS['Allocation_Rice'] = FPS['Allocation_FRice']

        FCI['WH_District'] = FCI['WH_District'].apply(lambda x: x.replace(' ', ''))
        FPS['FPS_District'] = FPS['FPS_District'].apply(lambda x: x.replace(' ', ''))
        #print(FCI)
        
        excel_path = "Backend//Distance_Initial_L2.xlsx"
        output_path = "Backend//Distance_Initial_L2_updated.xlsx"
        sheet_name = "BG_BG"
        excel_password = "distf"

        # ---------- Step 1: Get latest optimisation table ---------- #
        try:
            conn = connect_to_database()
            cursor = conn.cursor(dictionary=True)

            cursor.execute("""
                SELECT id
                FROM optimised_table
                WHERE month = %s and year = %s
                ORDER BY last_updated DESC
                LIMIT 1
            """, (month, year))
            opt = cursor.fetchone()
            updates = []
            if opt:
                table_name = f"optimiseddata_{opt['id']}"
                cursor.execute("SHOW TABLES LIKE %s", (table_name,))
                table_exists = cursor.fetchone()
                if table_exists:
                    cursor.execute(f"""
                        SELECT from_id, to_id, new_distance_district, approve_district
                        FROM `{table_name}`
                        WHERE LOWER(approve_district) = 'no'
                    """)
                    updates = cursor.fetchall()
            cursor.close()
            conn.close()

            if updates:
                # ---------- Step 2: Decrypt Excel ---------- #
                decrypted = io.BytesIO()
                with open(excel_path, "rb") as f:
                    office = msoffcrypto.OfficeFile(f)
                    office.load_key(password=excel_password)
                    office.decrypt(decrypted)

                decrypted.seek(0)

                # ---------- Step 3: Read Excel and Parse All Sheets ---------- #
                xl = pd.ExcelFile(decrypted, engine="openpyxl")
                sheets = {name: xl.parse(name) for name in xl.sheet_names}
                df = sheets[sheet_name]

                df.rename(columns={df.columns[0]: "to_id"}, inplace=True)
                df["to_id"] = df["to_id"].astype(str)
                df.set_index("to_id", inplace=True)

                df.columns = df.columns.astype(str)

                # ---------- Step 4: Intelligent Update + Minimal Append ---------- #
                updated_cells = 0
                appended_routes = 0

                for row in updates:
                    from_id = str(row["from_id"])
                    to_id = str(row["to_id"])
                    new_dist = row.get("new_distance_district")
                    if new_dist is not None:
                        try:
                            distance = float(new_dist)
                            if distance > 0:
                                # ---- Ensure ROW exists ---- #
                                if to_id not in df.index:
                                    df.loc[to_id] = 0
                                    appended_routes += 1

                                # ---- Ensure COLUMN exists ---- #
                                if from_id not in df.columns:
                                    df[from_id] = 0
                                    appended_routes += 1

                                # ---- Update the specific cell ---- #
                                if df.at[to_id, from_id] != distance:
                                    df.at[to_id, from_id] = distance
                                    updated_cells += 1
                        except (ValueError, TypeError):
                            pass

                # ---------- Step 5: Save Excel with All Sheets and Encrypt ---------- #
                output_path = "Backend//Distance_Initial_L2_updated.xlsx"
                sheets[sheet_name] = df.reset_index()

                plain_buf = io.BytesIO()
                with pd.ExcelWriter(plain_buf, engine="xlsxwriter") as writer:
                    for name, sheet_df in sheets.items():
                        sheet_df.to_excel(writer, sheet_name=name, index=False)
                plain_buf.seek(0)

                file = msoffcrypto.format.ooxml.OOXMLFile(plain_buf)
                with open(output_path, "wb") as f_out:
                    file.encrypt(excel_password, f_out)
            else:
                import shutil
                shutil.copy(excel_path, output_path)
        except Exception as e:
            write_log("Error updating distance matrix: " + str(e))


       
       
       
        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)

        District_Capacity = {}
        for i in range(len(FCI['WH_District'])):
            District_Name = FCI['WH_District'][i]
            if District_Name not in District_Capacity:
                District_Capacity[District_Name] = FCI['Storage_Capacity'][i]
            else:
                District_Capacity[District_Name] = FCI['Storage_Capacity'][i] + District_Capacity[District_Name]

        FPS_district = []
        FPS_Data = {}
        Districts_FPS = {}
        for (i, j) in zip(FPS['FPS_District'], FPS['FPS_Tehsil']):
            i = i.lower()
            if i not in FPS_district:
                FPS_district.append(i)
                globals()['FPS_' + str(i)] = []
            if j not in globals()['FPS_' + str(i)]:
                globals()['FPS_' + str(i)].append(j)
        for i in FPS_district:
            FPS_Data[i] = globals()['FPS_' + str(i)]
            Districts_FPS['Districts_FPS'] = FPS_district

        District_Demand = {}
        for i in range(len(FPS['FPS_District'])):
            District_Name_FPS = FPS['FPS_District'][i]
            if District_Name_FPS not in District_Demand:
                District_Demand[District_Name_FPS] = FPS['Allocation_Wheat'][i]
            else:
                District_Demand[District_Name_FPS] = FPS['Allocation_Wheat'][i] + District_Demand[District_Name_FPS]
        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)

        FCI_district = []
        FCI_Data = {}
        Disrticts_FCI = {}
        Data_state_wise = {}
        Data_statewise = {}

        for (i, j) in zip(FCI['WH_District'], FCI['WH_ID']):
            i = i.lower()
            if i not in FCI_district:
                FCI_district.append(i)
                globals()['FCI_' + str(i)] = []
            globals()['FCI_' + str(i)].append(j)
        for i in FCI_district:
            FCI_Data[i] = globals()['FCI_' + str(i)]
        Disrticts_FCI['Disrticts_FCI'] = FCI_district
        
        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)
        
        FPS_district = []
        FPS_Data = {}
        Districts_FPS = {}
        for (i, j) in zip(FPS['FPS_District'], FPS['FPS_Tehsil']):
            i = i.lower()
            if i not in FPS_district:
                FPS_district.append(i)
                globals()['FPS_' + str(i)] = []
            if j not in globals()['FPS_' + str(i)]:
                globals()['FPS_' + str(i)].append(j)
        for i in FPS_district:
            FPS_Data[i] = globals()['FPS_' + str(i)]
        Districts_FPS['Districts_FPS'] = FPS_district

        model = LpProblem('Supply-Demand-Problem', LpMinimize)

        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)

        Variable1 = []
        
        for i in range(len(FCI['WH_ID'])):
            for j in range(len(FPS['FPS_ID'])):
                Variable1.append(str(FCI['WH_ID'][i]) + '_'
                                 + str(FCI['WH_District'][i]) + '_'
                                 + str(FPS['FPS_ID'][j]) + '_'
                                 + str(FPS['FPS_District'][j]) + '_Wheat')
                                 
        

        # Variables for Wheat from lEVEL2 TO FPS

        DV_Variables1 = LpVariable.matrix('X', Variable1, cat='float',
                lowBound=0)
        Allocation1 = np.array(DV_Variables1).reshape(len(FCI['WH_ID']),
                len(FPS['FPS_ID']))
                
             
                
                

        Variable1I = []
        Allocation1I = []
        for i in range(len(FCI['WH_ID'])):
            for j in range(len(FPS['FPS_ID'])):
                Variable1I.append(str(FCI['WH_ID'][i]) + '_'
                                  + str(FCI['WH_District'][i]) + '_'
                                  + str(FPS['FPS_ID'][j]) + '_'
                                  + str(FPS['FPS_District'][j]) + '_Wheat1')

    #    Variables for Wheat from IG TO FPS

        DV_Variables1I = LpVariable.matrix('X', Variable1I, cat='Binary',lowBound=0)
        Allocation1I = np.array(DV_Variables1I).reshape(len(FCI['WH_ID']),len(FPS['FPS_ID']))

        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)

        for i in range(len(FPS['FPS_ID'])):
             model += lpSum(Allocation1I[k][i] for k in range(len(FCI['WH_ID']))) <= 1

        for i in range(len(FCI['WH_ID'])):
             for j in range(len(FPS['FPS_ID'])):
                model += Allocation1[i][j] <= 1000000 * Allocation1I[i][j]
                
        
        
        District_Capacity = {}
        for i in range(len(FCI["WH_District"])):
            District_Name = FCI["WH_District"][i]
            if District_Name not in District_Capacity:
                District_Capacity[District_Name] = int(FCI["Storage_Capacity"][i])
            else:
                District_Capacity[District_Name] += int(FCI["Storage_Capacity"][i])
 
        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)

        District_Demand = {}
        for i in range(len(FPS["FPS_District"])):
            District_Name_FPS = FPS["FPS_District"][i]
            if District_Name_FPS not in District_Demand:
                District_Demand[District_Name_FPS] = float(FPS["Allocation_Wheat"][i]) + float(FPS["Allocation_Rice"][i])
            else:
                District_Demand[District_Name_FPS] += float(FPS["Allocation_Wheat"][i]) + float(FPS["Allocation_Rice"][i])
                
        

        
        District_Name = []
        District_Name2=[]
        District_Name = [i for i in District_Demand if i not in District_Capacity]
        District_Name4 = [i for i in District_Capacity if i not in District_Demand]
        District_Name2 = [i for i in District_Demand if i in District_Capacity and District_Demand[i] >= District_Capacity[i]]
        District_Name_1 = {}
        District_Name_1['District_Name_All'] = District_Name + District_Name2
        District_Name3 = [i for i in District_Demand if i in District_Capacity and District_Demand[i] <= District_Capacity[i]]
        
        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)        
        
        Tehsil = {}
        UniqueId = 0
        Tehsil_temp = []
        Tehsil_rev = {}

        for i in FPS['FPS_Tehsil']:
            Tehsil_temp.append(i)
            if i not in Tehsil:
                Tehsil[i] = UniqueId
                Tehsil_rev[UniqueId] = i
                UniqueId = UniqueId + 1

        Tehsil_FPS = []
        for i in range(len(FPS['FPS_ID'])):
            Tehsil_FPS.append(Tehsil[Tehsil_temp[i]])

        

        allCombination1 = []
        

        for i in range(len(dist)):
            for j in range(len(FPS['FPS_ID'])):
                allCombination1.append(Allocation1[i][j] * dist[i][j])
        
        

        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)

        model += lpSum(allCombination1)

        # Demand Constraints for Wheat
        
        FPS["Demand"]=FPS["Allocation_Wheat"]+ FPS["Allocation_Rice"]

        for i in range(len(FPS['FPS_ID'])):
            model += lpSum(Allocation1[j][i] for j in range(len(FCI['WH_ID'
                           ]))) >= FPS['Demand'][i]
                           
       

        # Supply Constraints for Warehouses

        for i in range(len(FCI['WH_ID'])):
            model += (lpSum(Allocation1[i][j] for j in range(len(FPS['FPS_ID'
                           ])))  <= FCI['Storage_Capacity'][i])

       
        model.solve(COIN_CMD(path=get_cbc_path(),msg=0,gapRel=0.03,timeLimit=600))

        
        status = LpStatus[model.status]

        if status != "Optimal":
            print("Optimization failed:", status)

            data = {
                "status": 0,
                "message": "Infeasible or Unbounded Solution"
            }

            return json.dumps(data, indent=1)
 
        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)

        
        
        
        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)
        Original_Cost = 100000000
        total = Original_Cost

        data = {}
        #data['status'] = 1
        #data['modelStatus'] = Status
        #data['totalCost'] = float(round(model.objective.value(),1))
        #data['original'] = float(round(total, 2))
        #data['percentageReduction'] = float(round((total
                #- model.objective.value()) / total, 4) * 100)
        #data['Average_Distance'] = float(round(model.objective.value(), 2)) / Total_Demand
        #data['Demand'] = int(FPS['Allocation_Wheat'].sum())

        BGW = {}
        BGR = {}
        IGW = {}
        IGR = {}
        FCIW = {}

        BGCapacity = {}

        temp = {}
        for i in range(len(FCI['WH_ID'])):
            temp[str(FCI['WH_ID'][i])] = str(FCI['Storage_Capacity'])
        BGCapacity = temp

        temp1 = {}
        BG_FPS = [[] for i in range(len(Tehsil))]
        for i in range(len(FCI['WH_ID'])):
            for j in range(len(FPS['FPS_ID'])):
                BG_FPS[Tehsil_FPS[j]].append(Allocation1[i][j].value())
            temp1[str(FCI['WH_ID'][i])] = \
                str(lpSum(Allocation1[i][j].value() for j in
                    range(len(FPS['FPS_ID']))))
            BGCapacity[str(FCI['WH_ID'][i])] = str(FCI['Storage_Capacity'
                    ][i])
        BGW['FPS'] = temp1

        BG_FPS_Wheat = {}
        for i in range(len(Tehsil)):
            BG_FPS_Wheat[str(Tehsil_rev[i])] = str(lpSum(BG_FPS[i]))

        BG_FPS_Rice = {}
        for i in range(len(Tehsil)):
            BG_FPS_Rice[str(Tehsil_rev[i])] = str(lpSum(BG_FPS[i]))

        data['BGW'] = BGW
        data['BGR'] = BGR
        data['FPSW'] = BG_FPS_Wheat
        data['FPSR'] = BG_FPS_Rice
        data['BGCapacity'] = BGCapacity

        wheat_total_dict = data['BGW']['FPS']

        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)

        wheat_total = 0
        for value in wheat_total_dict:
            if float(wheat_total_dict[value]):
                wheat_total = int(wheat_total + float(wheat_total_dict[value]))

        total_commodity = int(wheat_total)

        Output_File = open('Backend//Inter_District1.csv', 'w')
        for v in model.variables():
            if v.value() > 0:
                Output_File.write(v.name + '\t' + str(v.value()) + '\n')

        Output_File = open('Backend//Inter_District1.csv', 'w')
        for v in model.variables():
            if v.value() > 0:
                Output_File.write(v.name + '\t' + str(v.value()) + '\n')


        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)

        df9 = pd.read_csv('Backend//Inter_District1.csv',header=None)
        df9.columns = ['Tagging']
        df9[[
            'Var',
            'WH_ID',
            'W_D',
            'FPS_ID',
            'FPS_D',
            'commodity_Value',
            ]] = df9[df9.columns[0]].str.split('_', n=6, expand=True)
        del df9[df9.columns[0]]
        df9[['commodity', 'Values']] = df9['commodity_Value'
                ].str.split('\\t', n=1, expand=True)
        del df9['commodity_Value']
        df9 = df9.drop(np.where(df9['commodity'] == 'Wheat1')[0])
        
        def convert_to_numeric(value):
            try:
                return pd.to_numeric(value)
            except ValueError:
                return value
        
        
        df9['WH_ID'] = df9['WH_ID'].apply(convert_to_numeric)
        df9['FPS_ID'] = df9['FPS_ID'].apply(convert_to_numeric)
        
        df9.to_excel('Backend//Tagging_Sheet_Pre.xlsx', sheet_name='BG_FPS')
        df31 = pd.read_excel('Backend//Tagging_Sheet_Pre.xlsx')
        
        USN = pd.ExcelFile('Backend//Data_1.xlsx')
        FCI = pd.read_excel(USN, sheet_name='A.1 Warehouse', index_col=None)
        FPS = pd.read_excel(USN, sheet_name='A.2 FPS', index_col=None)
        if 'Allocation_FRice' in FPS.columns and 'Allocation_Rice' not in FPS.columns:
            FPS['Allocation_Rice'] = FPS['Allocation_FRice']
       


        df4 = pd.merge(df31, FCI, on='WH_ID', how='inner')
        #df4 = pd.merge(df31, FCI, on='WH_ID', how='inner')
        df4 = df4[[
            'WH_ID',
            'WH_Name',
            'WH_District',
            'WH_Lat',
            'WH_Long',
            'FPS_ID',
            'Values',
            ]]
        df4 = pd.merge(df4, FPS, on='FPS_ID', how='inner')
        df51 = df4[[
            'WH_ID',
            'WH_Name',
            'WH_District',
            'WH_Lat',
            'WH_Long',
            'FPS_ID',
            'FPS_Name',
            'FPS_District',
            'FPS_Lat',
            'FPS_Long',
            'Allocation_Wheat',
            ]]
        df51.insert(0, 'Scenario', 'Optimized')
        df51.insert(1, 'From', 'Depot')
        df51.insert(2, 'From_State', 'Bihar')
        df51.insert(7, 'To', 'FPS')
        df51.insert(8, 'To_State', 'Bihar')
        df51.insert(9, 'commodity', 'Rice')
  
        df51.rename(columns={
            'WH_ID': 'From_ID',
            'WH_Name': 'From_Name',
            'WH_Lat': 'From_Lat',
            'WH_Long': 'From_Long',
            }, inplace=True)
        df51.rename(columns={
            'FPS_ID': 'To_ID',
            'FPS_Name': 'To_Name',
            'FPS_Lat': 'To_Lat',
            'FPS_Long': 'To_Long',
            'Allocation_Wheat': 'quantity',
            
            }, inplace=True)
        df51.rename(columns={'WH_District': 'From_District',
                   'FPS_District': 'To_District'}, inplace=True)
        df51 = df51.loc[:, [
            'Scenario',
            'From',
            'From_State',
            'From_District',
            'From_ID',
            'From_Name',
            'From_Lat',
            'From_Long',
            'To',
            'To_ID',
            'To_Name',
            'To_State',
            'To_District',
            'To_Lat',
            'To_Long',
            'commodity',
            'quantity',
            ]]
        
        
        df41 = pd.merge(df31, FCI, on='WH_ID', how='inner')
        #df4 = pd.merge(df31, FCI, on='WH_ID', how='inner')
        df41 = df41[[
            'WH_ID',
            'WH_Name',
            'WH_District',
            'WH_Lat',
            'WH_Long',
            'FPS_ID',
            'Values',
            ]]
        df41 = pd.merge(df41, FPS, on='FPS_ID', how='inner')
        df511 = df4[[
            'WH_ID',
            'WH_Name',
            'WH_District',
            'WH_Lat',
            'WH_Long',
            'FPS_ID',
            'FPS_Name',
            'FPS_District',
            'FPS_Lat',
            'FPS_Long',
            'Allocation_Rice',
            ]]
        df511.insert(0, 'Scenario', 'Optimized')
        df511.insert(1, 'From', 'Depot')
        df511.insert(2, 'From_State', 'Bihar')
        df511.insert(7, 'To', 'FPS')
        df511.insert(8, 'To_State', 'Bihar')
        df511.insert(9, 'commodity', 'Wheat')
  
        df511.rename(columns={
            'WH_ID': 'From_ID',
            'WH_Name': 'From_Name',
            'WH_Lat': 'From_Lat',
            'WH_Long': 'From_Long',
            }, inplace=True)
        df511.rename(columns={
            'FPS_ID': 'To_ID',
            'FPS_Name': 'To_Name',
            'FPS_Lat': 'To_Lat',
            'FPS_Long': 'To_Long',
            'Allocation_Rice': 'quantity',
            
            }, inplace=True)
        df511.rename(columns={'WH_District': 'From_District',
                   'FPS_District': 'To_District'}, inplace=True)
        df511= df511.loc[:, [
            'Scenario',
            'From',
            'From_State',
            'From_District',
            'From_ID',
            'From_Name',
            'From_Lat',
            'From_Long',
            'To',
            'To_ID',
            'To_Name',
            'To_State',
            'To_District',
            'To_Lat',
            'To_Long',
            'commodity',
            'quantity',
            ]]
        def convert_to_numeric(value):
            try:
                return pd.to_numeric(value)
            except ValueError:
                return value
                
        df_combined = pd.concat([df51, df511])
        df_combined1 = df_combined[df_combined['quantity'] != 0]
        df_combined1['From_ID'] = df_combined1['From_ID'].apply(convert_to_numeric)
        df_combined1['To_ID'] = df_combined1['To_ID'].apply(convert_to_numeric)
        
        
        # Save DataFrame to Excel
        file_path = 'Backend/Tagging_Sheet_Pre11.xlsx'  # Adjust the path as needed
        df_combined1.to_excel(file_path, sheet_name='BG_FPS1', index=False, engine='xlsxwriter')
        
        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)       
            
        input = pd.ExcelFile('Backend/Data_1.xlsx')
        node1 = pd.read_excel(input,sheet_name="A.1 Warehouse")
        node1["concatenate"]= node1['WH_Lat'].round(3).astype(str) + ',' + node1['WH_Long'].round(3).astype(str)

        node2 = pd.read_excel(input,sheet_name="A.2 FPS")
        node2["concatenate1"]= node2['FPS_Lat'].round(3).astype(str) + ',' + node2['FPS_Long'].round(3).astype(str)

        updated_excel_path = 'Backend//Distance_Initial_L2_updated.xlsx'
        ref_excel_path = updated_excel_path if os.path.exists(updated_excel_path) else 'Backend//Distance_Initial_L2.xlsx'
        DistanceBing = read_protected_excel(ref_excel_path, 'distf', sheet_name='BG_BG')
        Warehouse = read_protected_excel(ref_excel_path, 'distf', sheet_name='Warehouse')
        FPS = read_protected_excel(ref_excel_path, 'distf', sheet_name='FPS')


        Warehouse['Lat_Long_r'] = (
            Warehouse['Lat_Long']
            .str.split(',', expand=True)
            .astype(float)
            .round(3)
            .astype(str)
            .agg(','.join, axis=1)
        )

        FPS['Lat_Long_r'] = (
            FPS['Lat_Long']
            .str.split(',', expand=True)
            .astype(float)
            .round(3)
            .astype(str)
            .agg(','.join, axis=1)
        )


        node1 = node1[['WH_ID', 'WH_Lat', 'WH_Long','concatenate']]
        War = pd.merge(node1, Warehouse, on='WH_ID')
        df1_w = War[War['concatenate'] != War['Lat_Long_r']]
        Warehouse_ID = df1_w['WH_ID'].unique()



        node2 = node2[['FPS_ID', 'FPS_Lat', 'FPS_Long','concatenate1']]
        FPS1 = pd.merge(node2, FPS, on='FPS_ID')
        df1_f = FPS1[FPS1['concatenate1'] != FPS1['Lat_Long_r']]
        FPS_ID = df1_f['FPS_ID'].unique()
        
        BG_BG = DistanceBing
        Distance1 = BG_BG.drop(columns=BG_BG.columns[BG_BG.columns.isin(Warehouse_ID)])
        Distance2 =Distance1.T
        Distance3 = Distance2.drop(columns=Distance2.columns[Distance2.columns.isin(FPS_ID)])
        Distance3 = Distance3.T
        with pd.ExcelWriter('Backend//Bihar_Distance_L2.xlsx') as writer:
            Distance3.to_excel(writer, sheet_name='BG_BG', index=False)
            
        
        
        
   
        
        Cost = pd.ExcelFile("Backend//Bihar_Distance_L2.xlsx")
        BG_BG = pd.read_excel(Cost,sheet_name="BG_BG")
        Cost.close()
        data1 = pd.ExcelFile("Backend//Tagging_Sheet_Pre11.xlsx")
        df5 = pd.read_excel(data1,sheet_name="BG_FPS1")
        data1.close()

        Distance_BG_BG = {}
        column_list_BG_BG = list(BG_BG.columns.astype(str))
        row_list_BG_BG = list(BG_BG.iloc[:, 0].astype(str))

        for ind in df5.index:
            from_code = df5['From_ID'][ind]
            to_code = df5['To_ID'][ind]
            from_code_str = str(from_code)
            to_code_str = str(to_code)
            
            if to_code_str in row_list_BG_BG and from_code_str in column_list_BG_BG:
                index_i = row_list_BG_BG.index(to_code_str)
                index_j = column_list_BG_BG.index(from_code_str)
                key = to_code_str + "_" + from_code_str
                Distance_BG_BG[key] = BG_BG.iloc[index_i, index_j]
                
                
        df5["Tagging"] = df5['To_ID'].astype(str) + '_' + df5['From_ID'].astype(str)
        df5['Distance'] = df5['Tagging'].map(Distance_BG_BG)
        df5.fillna('shallu', inplace=True)
        df5.to_excel('Backend//Result_Sheet12.xlsx', sheet_name='Warehouse_FPS', index=False)

        
        # -------------------- LOAD DATA --------------------
        Result_Sheet1 = pd.ExcelFile("Backend//Result_Sheet12.xlsx")
        df6 = pd.read_excel(Result_Sheet1, sheet_name="Warehouse_FPS")
        
        Result_Sheet1.close()

        # -------------------- FILTER SHALLU --------------------
        df7 = df6.loc[df6['Distance'] == "shallu"].reset_index(drop=True)
        
                

        # Drop Tagging column (safe)
        df6.drop(columns=['Tagging'], errors='ignore', inplace=True)

        # Common column structure
        columns = [
            'Scenario','From','From_State','From_District','From_ID','From_Name',
            'From_Lat','From_Long','To','To_ID','To_Name','To_State','To_District',
            'To_Lat','To_Long','commodity','quantity','Distance'
        ]
        
        

        # ============================
        # [OK] CASE 1: NO "shallu"
        # ============================
        if df7.empty:
            print("No 'shallu' found -> skipping API")

            df10 = df6.copy()
            df10 = df10[columns]

        # ============================
        # [OK] CASE 2: PROCESS API
        # ============================
        else:
            print(f"'shallu' found -> processing {len(df7)} rows")
            print("anmol")

            # -------------------- API DETAILS --------------------
            auth_url = 'https://kerala.pmgatishakti.gov.in/DFPD/authenticate'
            distance_url = 'https://kerala.pmgatishakti.gov.in/PMGatishaktiApiService/dfpdapi/roaddistance'

            auth_payload = {
                "username": "DFPD_C",
                "password": "W9Vtb8WKkt3"
            }

            FILE_PATH = 'distanceIndent.json'

            # -------------------- GET TOKEN --------------------
            def get_token():
                try:
                    response = requests.post(auth_url, json=auth_payload, timeout=240)
                    if response.status_code == 200:
                        return response.json().get('token')
                    return None
                except requests.exceptions.RequestException as e:
                    print("Auth API Error:", e)
                    raise Exception("PMGatiShakti Authentication Service is currently unavailable. Please check your internet connection or try again later.")

            # -------------------- BATCH API --------------------
            def process_batch(df_batch, token):
                headers = {'Authorization': f'Bearer {token}'}

                data = {
                    "parameter": [{
                        "src_lng": row["From_Long"],
                        "src_lat": row["From_Lat"],
                        "dest_lng": row["To_Long"],
                        "dest_lat": row["To_Lat"]
                    } for _, row in df_batch.iterrows()]
                }

                try:
                    with open(FILE_PATH, 'w') as f:
                        json.dump(data, f, indent=4)

                    with open(FILE_PATH, 'rb') as f:
                        files = {'LatsLongsFile': f}
                        response = requests.post(distance_url, headers=headers, files=files, timeout=240)

                    return response

                except requests.exceptions.RequestException as e:
                    print("Batch API Error:", e)
                    raise Exception("PMGatiShakti Distance Service is currently unavailable. Please check your internet connection or try again later.")
                except Exception as e:
                    print("Batch API Error:", e)
                    return None

            # -------------------- SINGLE ROW API --------------------
            def process_single_row(row, token):
                headers = {'Authorization': f'Bearer {token}'}

                data = {
                    "parameter": [{
                        "src_lng": row["From_Long"],
                        "src_lat": row["From_Lat"],
                        "dest_lng": row["To_Long"],
                        "dest_lat": row["To_Lat"]
                    }]
                }

                try:
                    with open(FILE_PATH, 'w') as f:
                        json.dump(data, f, indent=4)

                    with open(FILE_PATH, 'rb') as f:
                        files = {'LatsLongsFile': f}
                        response = requests.post(distance_url, headers=headers, files=files, timeout=240)

                    if response.status_code != 200:
                        return 0

                    res_json = response.json()
                    api_data = res_json.get("data", [])

                    if len(api_data) == 0:
                        return 0

                    distance = api_data[0].get("distance")

                    if isinstance(distance, (int, float)):
                        return distance

                    return 0

                except requests.exceptions.RequestException as e:
                    print("Row API Error:", e)
                    raise Exception("PMGatiShakti Distance Service is currently unavailable. Please check your internet connection or try again later.")
                except Exception as e:
                    print("Row API Error:", e)
                    return 0

            # -------------------- MAIN PROCESS --------------------
            batch_size = 1000
            total_rows = len(df7)
            num_batches = (total_rows + batch_size - 1) // batch_size

            dist3 = []

            for batch_num in range(num_batches):
                print(f"Processing batch {batch_num+1}/{num_batches}")

                start_idx = batch_num * batch_size
                end_idx = min((batch_num + 1) * batch_size, total_rows)
                df_batch = df7.iloc[start_idx:end_idx]

                token = get_token()
                if not token:
                    print("Token failed -> filling with 0")
                    dist3.extend([0] * len(df_batch))
                    continue

                response = process_batch(df_batch, token)

                fallback_required = False

                if not response or response.status_code != 200:
                    fallback_required = True
                else:
                    try:
                        response_json = response.json()
                        api_data = response_json.get("data", [])

                        if len(api_data) != len(df_batch):
                            fallback_required = True
                        else:
                            for row_data in api_data:
                                if not isinstance(row_data.get("distance"), (int, float)):
                                    fallback_required = True
                                    break
                    except:
                        fallback_required = True

                # ---------------- FALLBACK ----------------
                if fallback_required:
                    print(f"Batch {batch_num+1} failed -> row-wise fallback")

                    for _, row in df_batch.iterrows():
                        distance = process_single_row(row, token)

                        if distance == 0:
                            print(f"0 distance -> {row['From_ID']} to {row['To_ID']}")

                        dist3.append(distance)

                # ---------------- NORMAL ----------------
                else:
                    for row_data in api_data:
                        dist3.append(row_data.get("distance"))


            df7["Distance"] = dist3
            print("Pawaa")

            # Merge with non-shallu
            df9 = df6.loc[df6['Distance'] != "shallu"]

            df9 = df9[columns]
            df7 = df7[columns]

            df10 = pd.concat([df9, df7], ignore_index=True)

        # ============================
        # FINAL OUTPUT (COMMON)
        # ============================
        result = (df10['quantity'] * df10['Distance']).sum()

        print("Total Result:", result)

        df10.to_excel('Backend//Result_Sheet.xlsx', sheet_name='Warehouse_FPS', index=False)

        print("Process Completed Successfully")
# ----------------------------------------------------------------------------------------------------------------------------------------------        
        
        data ={}
        
        data["Scenario"]="Inter"
        data["Scenario_Baseline"] = "Baseline"
        
        data["WH_Used"] = df5['From_ID'].nunique()
        data["WH_Used_Baseline"] = "76"
        
        data["FPS_Used"] = df5['To_ID'].nunique()
        data["FPS_Used_Baseline"] = "1,795"
        
        total_demand = df10["quantity"].astype(float).sum()

        data['Demand'] = total_demand
        data['Demand_Baseline'] ="69,247"
        
        data['Total_QKM'] = float(result)
        data['Total_QKM_Baseline'] = "18,40,201"
        
        data['Average_Distance'] = (float(round(result, 2)) / total_demand)
        data['Average_Distance_Baseline'] = "26.58"

        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)                     

        save_to_database(month, year, applicable)
        save_monthly_data(month, year, float(result))
        
        def delete_files(file_paths):
            for file_path in file_paths:
                try:
                    if os.path.exists(file_path):  # Check if the file exists
                        os.remove(file_path)  # Delete the file
                        #print(f"{file_path} has been deleted.")
                    else:
                        print(f"{file_path} does not exist.")
                except Exception as e:
                    print(f"Error deleting {file_path}: {e}")

        # List of files to delete
        files_to_delete = [
            'Backend/Bihar_Distance_L2.xlsx',
            'Backend/Result_Sheet12.xlsx',
            'Backend//Tagging_Sheet_Pre11.xlsx',
            
        ]

        # Call the function to delete the files
        delete_files(files_to_delete)
        
        json_data = json.dumps(data)
        json_object = json.loads(json_data)

        if os.path.exists('ouputPickle.pkl'):
            os.remove('ouputPickle.pkl')

        # open pickle file
        dbfile1 = open('ouputPickle.pkl', 'ab')

    # save pickle data
    pickle.dump(json_object, dbfile1)
    dbfile1.close()
    data['status'] = 1
    json_data = json.dumps(data)
    json_object = json.loads(json_data)
    return json.dumps(json_object, indent=1)
    
@app.route('/processFileleg1', methods=['POST'])
def processFile_leg1():
    global stop_process
    stop_process = False

    if request.form.get("async") == "1":
        client_id = request.form.get("client_id") or request.form.get("username") or request.form.get("user") or ""
        if not client_id:
            client_id = "anonymous"
        form_dict = request.form.to_dict(flat=True)
        job_id = _job_create(client_id, endpoint="/processFileleg1", message="queued", payload=json.dumps(form_dict))
        py_exe = sys.executable or "python"
        if getattr(sys, 'frozen', False):
            subprocess.Popen([py_exe, "--run-job", job_id, SERVER_INSTANCE_ID], close_fds=True)
        else:
            script_path = os.path.abspath(__file__)
            subprocess.Popen([py_exe, script_path, "--run-job", job_id, SERVER_INSTANCE_ID], close_fds=True)
        return jsonify({"status": 1, "job_id": job_id, "message": "processing started"})
    json_data = request.form
    write_log("User -> " + " Optimization-Leg1 Start Requested JSON -> " + str(json_data))
    scenario_type = request.form.get('type')
    if scenario_type == "intra":
        message = 'DataFile file is incorrect'
        try:
            USN = pd.ExcelFile('Backend//Data_2.xlsx')
            month = request.form.get('month')        
            year = request.form.get('year')
            applicable = request.form.get('applicable')
        except Exception as e:
            data = {}
            data['status'] = 0
            data['message'] = message
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)
        
        input = pd.ExcelFile('Backend//Data_2.xlsx')
        node1 = pd.read_excel(input,sheet_name="A.2 FCI")
        node2 = pd.read_excel(input,sheet_name="A.1 Warehouse")
        node3 = pd.read_excel(input,sheet_name="A.2 DCP")

        dist = [[0 for a in range(len(node2["SW_ID"]))] for b in range(len(node1["WH_ID"]))]
        phi_1 = []
        phi_2 = []
        delta_phi = []
        delta_lambda = []
        R = 6371 

        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)

        for i in node1.index:
            for j in node2.index:
                phi_1=math.radians(node1["WH_Lat"][i])
                phi_2=math.radians(node2["SW_lat"][j])
                delta_phi=math.radians(node2["SW_lat"][j]-node1["WH_Lat"][i])
                delta_lambda=math.radians(node2["SW_Long"][j]-node1["WH_Long"][i])
                x=math.sin(delta_phi / 2.0) ** 2 + math.cos(phi_1) * math.cos(phi_2) * math.sin(delta_lambda / 2.0) ** 2
                y=2 * math.atan2(math.sqrt(x), math.sqrt(1 - x))
                dist[i][j]=R*y
                
       


        dist1 = [[0 for a in range(len(node2["SW_ID"]))] for b in range(len(node3["WH_ID"]))]
        phi_11 = []
        phi_21 = []
        delta_phi1 = []
        delta_lambda1 = []
        R = 6371 

        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)

        for i in node3.index:
            for j in node2.index:
                phi_11=math.radians(node3["WH_Lat"][i])
                phi_21=math.radians(node2["SW_lat"][j])
                delta_phi1=math.radians(node2["SW_lat"][j]-node3["WH_Lat"][i])
                delta_lambda1=math.radians(node2["SW_Long"][j]-node3["WH_Long"][i])
                x=math.sin(delta_phi1 / 2.0) ** 2 + math.cos(phi_11) * math.cos(phi_21) * math.sin(delta_lambda1 / 2.0) ** 2
                y=2 * math.atan2(math.sqrt(x), math.sqrt(1 - x))
                dist1[i][j]=R*y
                
        

        
        FCI = pd.read_excel(USN, sheet_name='A.2 FCI', index_col=None)
        WH = pd.read_excel(USN, sheet_name='A.1 Warehouse', index_col=None)
        DCP = pd.read_excel(USN, sheet_name='A.2 DCP', index_col=None)

        
      
        FCI['WH_District'] = FCI['WH_District'].apply(lambda x: x.replace(' ', ''))
        WH['SW_District'] = WH['SW_District'].apply(lambda x: x.replace(' ', ''))
        DCP['WH_District'] = DCP['WH_District'].apply(lambda x: x.replace(' ', ''))
        
        
        excel_path = "Backend//Distance_Intial_L1.xlsx"
        output_path = "Backend//Distance_Initial_L1_updated.xlsx"
        sheet_name = "BG_BG"
        excel_password = "distf"

        # ---------- Step 1: Get latest optimisation table ---------- #
        conn = connect_to_database()
        cursor = conn.cursor(dictionary=True)

        cursor.execute("""
            SELECT id
            FROM optimised_table_leg1
            WHERE month = %s and year = %s
            ORDER BY last_updated DESC
            LIMIT 1
        """, (month, year))
        opt = cursor.fetchone()

        updates = []
        if opt:
            table_name = f"optimiseddata_leg1_{opt['id']}"
            cursor.execute("SHOW TABLES LIKE %s", (table_name,))
            table_exists = cursor.fetchone()
            if table_exists:
                cursor.execute(f"""
                    SELECT from_id, to_id, new_distance_district, approve_district
                    FROM `{table_name}`
                    WHERE LOWER(approve_district) = 'no'
                """)
                updates = cursor.fetchall()

        cursor.close()
        conn.close()

        if updates:
            # ---------- Step 2: Decrypt Excel ---------- #
            decrypted = io.BytesIO()
            with open(excel_path, "rb") as f:
                office = msoffcrypto.OfficeFile(f)
                office.load_key(password=excel_password)
                office.decrypt(decrypted)

            decrypted.seek(0)

            # ---------- Step 3: Read Excel and Parse All Sheets ---------- #
            xl = pd.ExcelFile(decrypted, engine="openpyxl")
            sheets = {name: xl.parse(name) for name in xl.sheet_names}
            df = sheets[sheet_name]

            df.rename(columns={df.columns[0]: "to_id"}, inplace=True)
            df["to_id"] = df["to_id"].astype(str)
            df.set_index("to_id", inplace=True)

            df.columns = df.columns.astype(str)

            # ---------- Step 4: Intelligent Update + Minimal Append ---------- #
            updated_cells = 0
            appended_routes = 0

            for row in updates:
                from_id = str(row["from_id"])
                to_id = str(row["to_id"])
                new_dist = row.get("new_distance_district")
                if new_dist is not None:
                    try:
                        distance = float(new_dist)
                        if distance > 0:
                            # ---- Ensure ROW exists ---- #
                            if to_id not in df.index:
                                df.loc[to_id] = 0
                                appended_routes += 1

                            # ---- Ensure COLUMN exists ---- #
                            if from_id not in df.columns:
                                df[from_id] = 0
                                appended_routes += 1

                            # ---- Update the specific cell ---- #
                            if df.at[to_id, from_id] != distance:
                                df.at[to_id, from_id] = distance
                                updated_cells += 1
                    except (ValueError, TypeError):
                        pass

            # ---------- Step 5: Save Excel with All Sheets and Encrypt ---------- #
            output_path = "Backend//Distance_Initial_L1_updated.xlsx"
            sheets[sheet_name] = df.reset_index()

            plain_buf = io.BytesIO()
            with pd.ExcelWriter(plain_buf, engine="xlsxwriter") as writer:
                for name, sheet_df in sheets.items():
                    sheet_df.to_excel(writer, sheet_name=name, index=False)
            plain_buf.seek(0)

            file = msoffcrypto.format.ooxml.OOXMLFile(plain_buf)
            with open(output_path, "wb") as f_out:
                file.encrypt(excel_password, f_out)
        else:
            import shutil
            shutil.copy(excel_path, output_path)
        
        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)

                
        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)
        
        model = LpProblem('Supply-Demand-Problem', LpMinimize)
        
        
        Variable3 = []
        
        for i in range(len(DCP['WH_ID'])):
            for j in range(len(WH['SW_ID'])):
                Variable3.append(str(DCP['WH_ID'][i]) + '_'
                                 + str(DCP['WH_District'][i]) + '_'
                                 + str(WH['SW_ID'][j]) + '_'
                                 + str(WH['SW_District'][j]) + f'_FRice_{i}_{j}')
                                 
        Variable4 = []
        for i in range(len(FCI['WH_ID'])):
            for j in range(len(WH['SW_ID'])):
                Variable4.append(str(FCI['WH_ID'][i]) + '_'
                                 + str(FCI['WH_District'][i]) + '_'
                                 + str(WH['SW_ID'][j]) + '_'
                                 + str(WH['SW_District'][j]) + f'_Wheat_{i}_{j}')
                                 
                                 
        Variable5 = []
        
        for i in range(len(DCP['WH_ID'])):
            for j in range(len(WH['SW_ID'])):
                Variable5.append(str(DCP['WH_ID'][i]) + '_'
                                 + str(DCP['WH_District'][i]) + '_'
                                 + str(WH['SW_ID'][j]) + '_'
                                 + str(WH['SW_District'][j]) + f'_Wheat_{i}_{j}')
                                 
        Variable6 = []
        for i in range(len(FCI['WH_ID'])):
            for j in range(len(WH['SW_ID'])):
                Variable6.append(str(FCI['WH_ID'][i]) + '_'
                                 + str(FCI['WH_District'][i]) + '_'
                                 + str(WH['SW_ID'][j]) + '_'
                                 + str(WH['SW_District'][j]) + f'_FRice_{i}_{j}')                         

        # Variables for Wheat from lEVEL2 TO FPS

        DV_Variables3 = LpVariable.matrix('X', Variable3, cat='float',
                lowBound=0)
        Allocation3 = np.array(DV_Variables3).reshape(len(DCP['WH_ID']),
                len(WH['SW_ID']))
                
        
                
        DV_Variables4 = LpVariable.matrix('Y', Variable4, cat='float',
                lowBound=0)
        Allocation4 = np.array(DV_Variables4).reshape(len(FCI['WH_ID']),
                len(WH['SW_ID']))

        DV_Variables5 = LpVariable.matrix('Z', Variable5, cat='float',
                lowBound=0)
        Allocation5 = np.array(DV_Variables5).reshape(len(DCP['WH_ID']),
                len(WH['SW_ID']))
                
        DV_Variables6 = LpVariable.matrix('P', Variable6, cat='float',
                lowBound=0)
        Allocation6 = np.array(DV_Variables6).reshape(len(FCI['WH_ID']),
                len(WH['SW_ID']))
        
        
        
        
        


        allCombination3 = []
        allCombination4 = []
        allCombination5 = []
        allCombination6 = []


        for i in range(len(dist1)):
            for j in range(len(WH['SW_ID'])):
                allCombination3.append(Allocation3[i][j] * dist1[i][j])
        
        for i in range(len(dist1)):
            for j in range(len(WH['SW_ID'])):
                allCombination5.append(Allocation5[i][j] * dist1[i][j])
                
        for i in range(len(dist)):
            for j in range(len(WH['SW_ID'])):
                allCombination4.append(Allocation4[i][j] * dist[i][j])
                
        for i in range(len(dist)):
            for j in range(len(WH['SW_ID'])):
                allCombination6.append(Allocation6[i][j] * dist[i][j])        

        model += lpSum(allCombination3 + allCombination4 + allCombination5+ allCombination6)
        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)

        Total_Wheat_Demand= WH["Demand_Wheat"].sum
        Total_Rice_Demand=WH["Demand_FRice"].sum
        Total_Wheat_DCP=DCP['Procurement Wheat'].sum
        Total_Rice_DCP=DCP['Procurement Rice'].sum
        
        # ==========================================================
        # Total DCP can satisfy total demand
        # ==========================================================

        if Total_Wheat_DCP >= Total_Wheat_Demand and Total_Rice_DCP >= Total_Rice_Demand:

            # ---------------- Demand ----------------

            for j in range(len(WH['SW_ID'])):
                model += (
                    lpSum(Allocation5[i][j] for i in range(len(DCP['WH_ID'])))
                    == WH['Demand_Wheat'][j]
                )

            for j in range(len(WH['SW_ID'])):
                model += (
                    lpSum(Allocation3[i][j] for i in range(len(DCP['WH_ID'])))
                    == WH['Demand_FRice'][j]
                )

            # ---------------- DCP Capacity ----------------

            for i in range(len(DCP['WH_ID'])):
                model += (
                    lpSum(Allocation5[i][j] for j in range(len(WH['SW_ID'])))
                    <= DCP['Procurement Wheat'][i]
                )

            for i in range(len(DCP['WH_ID'])):
                model += (
                    lpSum(Allocation3[i][j] for j in range(len(WH['SW_ID'])))
                    <= DCP['Procurement Rice'][i]
                )

            # ---------------- District-wise Restriction ----------------

            District_List = list(WH['SW_District'].unique())

            for district in District_List:

                WH_Index = WH.index[
                    WH['SW_District'] == district
                ].tolist()

                DCP_Index = DCP.index[
                    DCP['WH_District'] == district
                ].tolist()

                Wheat_DCP = DCP.loc[DCP_Index, 'Procurement Wheat'].sum()
                Rice_DCP = DCP.loc[DCP_Index, 'Procurement Rice'].sum()

                Wheat_Demand = WH.loc[WH_Index, 'Demand_Wheat'].sum()
                Rice_Demand = WH.loc[WH_Index, 'Demand_FRice'].sum()

                # Wheat
                if Wheat_DCP >= Wheat_Demand:

                    for j in WH_Index:

                        for i in range(len(DCP['WH_ID'])):

                            if DCP.loc[i, 'WH_District'] != district:
                                model += Allocation5[i][j] == 0

                # Rice
                if Rice_DCP >= Rice_Demand:

                    for j in WH_Index:

                        for i in range(len(DCP['WH_ID'])):

                            if DCP.loc[i, 'WH_District'] != district:
                                model += Allocation3[i][j] == 0

            # ---------------- No FCI ----------------

            for i in range(len(FCI['WH_ID'])):
                for j in range(len(WH['SW_ID'])):
                    model += Allocation4[i][j] == 0
                    model += Allocation6[i][j] == 0

        # ==========================================================
        # Total DCP cannot satisfy total demand
        # ==========================================================

        else:

            # ---------------- Demand ----------------

            for j in range(len(WH['SW_ID'])):

                model += (
                    lpSum(Allocation5[i][j] for i in range(len(DCP['WH_ID'])))
                    +
                    lpSum(Allocation4[i][j] for i in range(len(FCI['WH_ID'])))
                    ==
                    WH['Demand_Wheat'][j]
                )

            for j in range(len(WH['SW_ID'])):

                model += (
                    lpSum(Allocation3[i][j] for i in range(len(DCP['WH_ID'])))
                    +
                    lpSum(Allocation6[i][j] for i in range(len(FCI['WH_ID'])))
                    ==
                    WH['Demand_FRice'][j]
                )

            # ---------------- DCP Capacity ----------------

            for i in range(len(DCP['WH_ID'])):

                model += (
                    lpSum(Allocation5[i][j] for j in range(len(WH['SW_ID'])))
                    <= DCP['Procurement Wheat'][i]
                )

            for i in range(len(DCP['WH_ID'])):

                model += (
                    lpSum(Allocation3[i][j] for j in range(len(WH['SW_ID'])))
                    <= DCP['Procurement Rice'][i]
                )

            # ---------------- Surplus / Deficit Districts ----------------

            District_List = list(WH['SW_District'].unique())

            Surplus_Wheat = []
            Deficit_Wheat = []

            Surplus_Rice = []
            Deficit_Rice = []

            for district in District_List:

                Wheat_DCP = DCP.loc[
                    DCP['WH_District'] == district,
                    'Procurement Wheat'
                ].sum()

                Wheat_Demand = WH.loc[
                    WH['SW_District'] == district,
                    'Demand_Wheat'
                ].sum()

                if Wheat_DCP > Wheat_Demand:
                    Surplus_Wheat.append(district)

                elif Wheat_DCP < Wheat_Demand:
                    Deficit_Wheat.append(district)

                Rice_DCP = DCP.loc[
                    DCP['WH_District'] == district,
                    'Procurement Rice'
                ].sum()

                Rice_Demand = WH.loc[
                    WH['SW_District'] == district,
                    'Demand_FRice'
                ].sum()

                if Rice_DCP > Rice_Demand:
                    Surplus_Rice.append(district)

                elif Rice_DCP < Rice_Demand:
                    Deficit_Rice.append(district)

            # ---------------- Wheat Inter-District ----------------

            for i in range(len(DCP)):

                Source = DCP.loc[i, 'WH_District']

                for j in range(len(WH)):

                    Destination = WH.loc[j, 'SW_District']

                    if Source != Destination:

                        if not (
                            Source in Surplus_Wheat
                            and
                            Destination in Deficit_Wheat
                        ):

                            model += Allocation5[i][j] == 0

            # ---------------- Rice Inter-District ----------------

            for i in range(len(DCP)):

                Source = DCP.loc[i, 'WH_District']

                for j in range(len(WH)):

                    Destination = WH.loc[j, 'SW_District']

                    if Source != Destination:

                        if not (
                            Source in Surplus_Rice
                            and
                            Destination in Deficit_Rice
                        ):

                            model += Allocation3[i][j] == 0

            # ---------------- FCI Capacity ----------------

            for i in range(len(FCI['WH_ID'])):

                model += (
                    lpSum(Allocation4[i][j] for j in range(len(WH['SW_ID'])))
                    <= FCI['Allotment_Wheat'][i]
                )

            for i in range(len(FCI['WH_ID'])):

                model += (
                    lpSum(Allocation6[i][j] for j in range(len(WH['SW_ID'])))
                    <= FCI['Allotment_FRice'][i]
                )
       
        model.solve(COIN_CMD(path=get_cbc_path(),msg=0,gapRel=0.03,timeLimit=600))

        
        status = LpStatus[model.status]

        if status != "Optimal":
            print("Optimization failed:", status)

            data = {
                "status": 0,
                "message": "Infeasible or Unbounded Solution"
            }

            return json.dumps(data, indent=1)
 
        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)

        
        
        
        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)
        

        data = {}
        

        
        Output_File = open('Backend//Inter_District1_leg1.csv', 'w')
        for v in model.variables():
            if v.value() > 0:
                Output_File.write(v.name + '\t' + str(v.value()) + '\n')

        Output_File = open('Backend//Inter_District1_leg1.csv', 'w')
        for v in model.variables():
            if v.value() > 0:
                Output_File.write(v.name + '\t' + str(v.value()) + '\n')


        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)

        
        df9 = pd.read_csv('Backend//Inter_District1_leg1.csv',header=None)

        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)        

        df9.columns = ['Tagging']
        df9[[
            'Var',
            'WH_ID',
            'W_D',
            'SW_ID',
            'SW_D',
            'commodity_Value',
            ]] = df9[df9.columns[0]].str.split('_', n=5, expand=True)
        del df9[df9.columns[0]]
        df9[['commodity', 'Values']] = df9['commodity_Value'].str.split('\\t', n=1, expand=True)
        del df9['commodity_Value']
        df9['commodity'] = df9['commodity'].str.split('_').str[0]
        
        df9 = df9.drop(np.where(df9['commodity'] == 'Wheat1')[0])
        def convert_to_numeric(value):
            try:
                return pd.to_numeric(value)
            except ValueError:
                return value
        
        
        df9['WH_ID'] = df9['WH_ID'].apply(convert_to_numeric)
        df9['SW_ID'] = df9['SW_ID'].apply(convert_to_numeric)
        
        df9.to_excel('Backend//Tagging_Sheet_Pre_leg1.xlsx', sheet_name='BG_FPS')
        df31 = pd.read_excel('Backend//Tagging_Sheet_Pre_leg1.xlsx')
        
        USN = pd.ExcelFile('Backend//Data_2.xlsx')
        WH = pd.read_excel(USN, sheet_name='A.1 Warehouse', index_col=None)
        FCI = pd.read_excel(USN, sheet_name='A.2 FCI', index_col=None)        # Convert to object type, adjust as needed
        

        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)

        df4 = pd.merge(df31, FCI, on='WH_ID', how='inner')
        #df4 = pd.merge(df31, FCI, on='WH_ID', how='inner')
        df4 = df4[[
            'WH_ID',
            'WH_Name',
            'WH_District',
            'WH_Lat',
            'WH_Long',
            'SW_ID',
            'commodity',
            'Values',
            ]]
        df4 = pd.merge(df4, WH, on='SW_ID', how='inner')
        df51 = df4[[
            'WH_ID',
            'WH_Name',
            'WH_District',
            'WH_Lat',
            'WH_Long',
            'SW_ID',
            'SW_Name',
            'SW_District',
            'SW_lat',
            'SW_Long',
             'commodity',
            'Values',
            ]]
        df51.insert(0, 'Scenario', 'Optimized')
        df51.insert(1, 'From', 'FCI')
        df51.insert(2, 'From_State', 'Bihar')
        df51.insert(7, 'To', 'TPDS')
        df51.insert(8, 'To_State', 'Bihar')
        
        df51.rename(columns={
            'WH_ID': 'From_ID',
            'WH_Name': 'From_Name',
            'WH_Lat': 'From_Lat',
            'WH_Long': 'From_Long',
            }, inplace=True)
        df51.rename(columns={
            'SW_ID': 'To_ID',
            'SW_Name': 'To_Name',
            'SW_lat': 'To_Lat',
            'SW_Long': 'To_Long',
            'Values':'quantity',
            }, inplace=True)
        df51.rename(columns={'WH_District': 'From_District',
                   'SW_District': 'To_District'}, inplace=True)
        df51 = df51.loc[:, [
            'Scenario',
            'From',
            'From_State',
            'From_District',
            'From_ID',
            'From_Name',
            'From_Lat',
            'From_Long',
            'To',
            'To_ID',
            'To_Name',
            'To_State',
            'To_District',
            'To_Lat',
            'To_Long',
            'commodity',
            'quantity',
            ]]
            
        def convert_to_numeric(value):
            try:
                return pd.to_numeric(value)
            except ValueError:
                return value
                
        
        df51['From_ID'] = df51['From_ID'].apply(convert_to_numeric)
        df51['To_ID'] = df51['To_ID'].apply(convert_to_numeric)   
        
        df51.to_excel('Backend//Tagging_Sheet_Pre11_leg1.xlsx', sheet_name='BG_FPS1')
        data1 = pd.ExcelFile("Backend//Tagging_Sheet_Pre11_leg1.xlsx")
        df5 = pd.read_excel(data1,sheet_name="BG_FPS1")
        data1.close()
        
        # ==========================================================
        # READ MASTER DATA
        # ==========================================================
        input_file = pd.ExcelFile('Backend//Data_2.xlsx')

        # Warehouse Sheet
        node1 = pd.read_excel(input_file, sheet_name="A.1 Warehouse")

        node1['SW_ID'] = node1['SW_ID'].astype(str).str.strip()

        node1['Lat_Long_r'] = (
            node1[['SW_lat', 'SW_Long']]
            .astype(float)
            .round(3)
            .astype(str)
            .agg(','.join, axis=1)
        )

        # FCI Sheet
        node2 = pd.read_excel(input_file, sheet_name="A.2 FCI")

        node2['WH_ID'] = node2['WH_ID'].astype(str).str.strip()

        node2['Lat_Long_r'] = (
            node2[['WH_Lat', 'WH_Long']]
            .astype(float)
            .round(3)
            .astype(str)
            .agg(','.join, axis=1)
        )

        
        
        updated_excel_path = 'Backend//Distance_Initial_L1_updated.xlsx'
        ref_excel_path = updated_excel_path if os.path.exists(updated_excel_path) else 'Backend//Distance_Intial_L1.xlsx'
        DistanceBing = read_protected_excel(ref_excel_path, 'distf', sheet_name='BG_BG')
        Warehouse = read_protected_excel(ref_excel_path, 'distf', sheet_name='Warehouse')
        FCI = read_protected_excel(ref_excel_path, 'distf', sheet_name='FCI')
        
                # ==========================================================
        # STANDARDIZE IDS
        # ==========================================================
        Warehouse['SW_ID'] = Warehouse['SW_ID'].astype(str).str.strip()
        FCI['WH_ID'] = FCI['WH_ID'].astype(str).str.strip()

        # ==========================================================
        # ROUND LAT LONG IN DISTANCE FILE
        # ==========================================================
        Warehouse['Lat_Long_r'] = (
            Warehouse['Lat_Long']
            .astype(str)
            .str.split(',', expand=True)
            .astype(float)
            .round(3)
            .astype(str)
            .agg(','.join, axis=1)
        )

        FCI['Lat_Long_r'] = (
            FCI['Lat_Long']
            .astype(str)
            .str.split(',', expand=True)
            .astype(float)
            .round(3)
            .astype(str)
            .agg(','.join, axis=1)
        )

        # ==========================================================
        # FIND WAREHOUSES WITH CHANGED LAT LONG
        # ==========================================================
        War = pd.merge(
            node1[['SW_ID', 'Lat_Long_r']],
            Warehouse[['SW_ID', 'Lat_Long_r']],
            on='SW_ID',
            how='inner',
            suffixes=('_master', '_distance')
        )

        Warehouse_ID = War.loc[
            War['Lat_Long_r_master'] != War['Lat_Long_r_distance'],
            'SW_ID'
        ].astype(str).unique()

        print("Warehouse IDs to remove:", len(Warehouse_ID))

        # ==========================================================
        # FIND FCI WITH CHANGED LAT LONG
        # ==========================================================
        FPS1 = pd.merge(
            node2[['WH_ID', 'Lat_Long_r']],
            FCI[['WH_ID', 'Lat_Long_r']],
            on='WH_ID',
            how='inner',
            suffixes=('_master', '_distance')
        )

        FPS_ID = FPS1.loc[
            FPS1['Lat_Long_r_master'] != FPS1['Lat_Long_r_distance'],
            'WH_ID'
        ].astype(str).unique()

        print("FCI IDs to remove:", len(FPS_ID))

        # ==========================================================
        # REMOVE FROM DISTANCE MATRIX
        # ==========================================================

        # Convert all column names to string
        DistanceBing.columns = DistanceBing.columns.astype(str)

        # If first column contains row IDs, convert to string
        DistanceBing.iloc[:, 0] = DistanceBing.iloc[:, 0].astype(str)

        # Remove warehouse columns
        Distance1 = DistanceBing.drop(
            columns=[col for col in DistanceBing.columns if col in Warehouse_ID],
            errors='ignore'
        )

        # Remove FCI rows
        Distance2 = Distance1[
            ~Distance1.iloc[:, 0].isin(FPS_ID)
        ]

        # ==========================================================
        # SAVE OUTPUT
        # ==========================================================
        with pd.ExcelWriter(
            'Backend//Bihar_Distance_L1.xlsx',
            engine='openpyxl'
        ) as writer:

            Distance2.to_excel(
                writer,
                sheet_name='BG_BG',
                index=False
            )

        print("Distance matrix updated successfully.")
        print("Final Shape:", Distance2.shape)
           
        Cost=pd.ExcelFile('Backend//Bihar_Distance_L1.xlsx')
        BG_BG = pd.read_excel(Cost,sheet_name="BG_BG")
        Cost.close()

        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)        

        Distance_BG_BG = {}
        column_list_BG_BG = list(BG_BG.columns)
       
        row_list_BG_BG = list(BG_BG.iloc[:, 0])
       
        for ind in df5.index:
            from_code= df5['From_ID'][ind] 
            to_code = df5['To_ID'][ind]
            if to_code in row_list_BG_BG and from_code in column_list_BG_BG:
                index_i = row_list_BG_BG.index(to_code)
                index_j = column_list_BG_BG.index(from_code)
                key = str(to_code) + "_" + str(from_code)
                Distance_BG_BG[key]= BG_BG.iloc[index_i , index_j]
                
            
        
        df5["Tagging"] = df5['To_ID'].astype(str) + '_' + df5['From_ID'].astype(str)
        df5['Distance'] = df5['Tagging'].map(Distance_BG_BG)
        df5 = df5.replace('',pd.NaT).fillna('shallu')
        d5=df5.loc[df5['Distance'] == "shallu"]
        df5.to_excel('Backend//Result_Sheet12.xlsx',
                         sheet_name='Warehouse_FPS')

        
# ----------------------------------------------------------------------------------------------------------------------------------------------
        # ----------------------------------------------------------------------------------------------------------------------------------------------
# -------------------- READ INPUT --------------------
        Result_Sheet1 = pd.ExcelFile("Backend//Result_Sheet12.xlsx")
        df6 = pd.read_excel(Result_Sheet1, sheet_name="Warehouse_FPS")
        Result_Sheet1.close()

        df7 = df6.loc[df6['Distance'] == "shallu"].reset_index(drop=True)

        # -------------------- API Details --------------------
        auth_url = 'https://kerala.pmgatishakti.gov.in/DFPD/authenticate'
        distance_url = 'https://kerala.pmgatishakti.gov.in/PMGatishaktiApiService/dfpdapi/roaddistance'

        auth_payload = {
            "username": "DFPD_C",
            "password": "W9Vtb8WKkt3"
        }

        FILE_PATH = 'distanceIndent.json'

        # -------------------- Get Token --------------------
        def get_token():
            try:
                response = requests.post(auth_url, json=auth_payload, timeout=240)
                if response.status_code == 200:
                    return response.json().get('token')
                return None
            except requests.exceptions.RequestException as e:
                print("Auth API Error:", e)
                raise Exception("PMGatiShakti Authentication Service is currently unavailable. Please check your internet connection or try again later.")

        # -------------------- Batch API --------------------
        def process_batch(df_batch, token):
            headers = {'Authorization': f'Bearer {token}'}

            data = {
                "parameter": [{
                    "src_lng": row["From_Long"],
                    "src_lat": row["From_Lat"],
                    "dest_lng": row["To_Long"],
                    "dest_lat": row["To_Lat"]
                } for _, row in df_batch.iterrows()]
            }

            try:
                with open(FILE_PATH, 'w') as f:
                    json.dump(data, f, indent=4)

                with open(FILE_PATH, 'rb') as f:
                    files = {'LatsLongsFile': f}
                    response = requests.post(distance_url, headers=headers, files=files, timeout=240)

                return response
            except requests.exceptions.RequestException as e:
                print("Batch API Error:", e)
                raise Exception("PMGatiShakti Distance Service is currently unavailable. Please check your internet connection or try again later.")
            except Exception as e:
                print("Batch API Error:", e)
                return None

        # -------------------- Single Row API --------------------
        def process_single_row(row, token):
            headers = {'Authorization': f'Bearer {token}'}

            data = {
                "parameter": [{
                    "src_lng": row["From_Long"],
                    "src_lat": row["From_Lat"],
                    "dest_lng": row["To_Long"],
                    "dest_lat": row["To_Lat"]
                }]
            }

            try:
                with open(FILE_PATH, 'w') as f:
                    json.dump(data, f, indent=4)

                with open(FILE_PATH, 'rb') as f:
                    files = {'LatsLongsFile': f}
                    response = requests.post(distance_url, headers=headers, files=files, timeout=240)

                if response.status_code != 200:
                    return 0

                res_json = response.json()
                api_data = res_json.get("data", [])

                if len(api_data) == 0:
                    return 0

                distance = api_data[0].get("distance")

                if isinstance(distance, (int, float)):
                    return distance

                return 0
            except requests.exceptions.RequestException as e:
                print("Row API Error (Connection):", e)
                return "CONNECTION_ERROR"
            except Exception as e:
                print("Row API Error:", e)
                return 0

        # ----------------------------------------------------------------------------------------------------------------------------------------------
        # -------------------- MAIN PROCESS --------------------

        batch_size = 1000
        total_rows = len(df7)
        num_batches = (total_rows + batch_size - 1) // batch_size

        dist3 = []

        for batch_num in range(num_batches):
            print(f"Processing batch {batch_num+1}/{num_batches}")

            start_idx = batch_num * batch_size
            end_idx = min((batch_num + 1) * batch_size, total_rows)
            df_batch = df7.iloc[start_idx:end_idx]

            token = get_token()
            if not token:
                data_err = {"status": 0, "message": "Failed to retrieve PMGatiShakti token."}
                return json.dumps(data_err, indent=1)

            response = process_batch(df_batch, token)

            fallback_required = False

            if not response or response.status_code != 200:
                fallback_required = True
            else:
                try:
                    response_json = response.json()
                    api_data = response_json.get("data", [])

                    if len(api_data) != len(df_batch):
                        fallback_required = True
                    else:
                        for row_data in api_data:
                            distance = row_data.get("distance")
                            if not isinstance(distance, (int, float)):
                                fallback_required = True
                                break

                except Exception:
                    fallback_required = True

            # ---------------- FALLBACK ----------------
            if fallback_required:
                print(f"Batch {batch_num+1} failed -> switching to row-wise")

                for _, row in df_batch.iterrows():
                    distance = process_single_row(row, token)
                    if distance == "CONNECTION_ERROR":
                        data_err = {"status": 0, "message": "PMGatiShakti API is currently unavailable or there is an internet connection issue. Please check your connection and try again."}
                        return json.dumps(data_err, indent=1)

                    if distance == 0:
                        print(f"Distance set to 0 for From {row['From_ID']} -> To {row['To_ID']}")

                    dist3.append(distance)

            # ---------------- NORMAL ----------------
            else:
                for row_data in api_data:
                    dist3.append(row_data.get("distance"))

        

        # -------------------- UPDATE DATA --------------------
        df7["Distance"] = dist3

        df9 = df6.loc[df6['Distance'] != "shallu"]

        columns = [
            'Scenario','From','From_State','From_District','From_ID','From_Name',
            'From_Lat','From_Long','To','To_ID','To_Name','To_State','To_District',
            'To_Lat','To_Long','commodity','quantity','Distance'
        ]

        df9 = df9[columns]
        df7 = df7[columns]

        df10 = pd.concat([df9, df7], ignore_index=True)

        # -------------------- FINAL RESULT --------------------
        result = (df10['quantity'] * df10['Distance']).sum()

        print("Total Result:", result)

        # -------------------- SAVE OUTPUT --------------------
        df10.to_excel('Backend//Result_Sheet_leg1.xlsx', sheet_name='Warehouse_FPS', index=False)

        print("Process Completed Successfully")
# ----------------------------------------------------------------------------------------------------------------------------------------------        


        Total_Demand=  float(WH['Demand_Wheat'].sum()) + float(WH['Demand_FRice'].sum()) 
        
        data ={}
        
        data["Scenario"]="Intra"
        data["Scenario_Baseline"] = "Baseline"
        
        data["WH_Used"] = df5['From_ID'].nunique()
        data["WH_Used_Baseline"] = "73"
        
        data["FPS_Used"] = df5['To_ID'].nunique()
        data["FPS_Used_Baseline"] = "458"
        
        data['Demand'] = float(WH['Demand_Wheat'].sum()) + float(WH['Demand_Rice'].sum()) 
        data['Demand_Baseline'] = "46,05,917.05"
        
        data['Total_QKM'] = float(result)
        data['Total_QKM_Baseline'] = "15,85,93,066.7"
        
        data['Average_Distance'] = float(round(result, 2)) / Total_Demand
        data['Average_Distance_Baseline'] = "34.43"

        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)                     

        save_to_database_leg1(month, year, applicable, scenario_type)
        save_monthly_data_leg1(month, year, float(result))
        
        def delete_files(file_paths):
            for file_path in file_paths:
                try:
                    if os.path.exists(file_path):  # Check if the file exists
                        os.remove(file_path)  # Delete the file
                        #print(f"{file_path} has been deleted.")
                    else:
                        print(f"{file_path} does not exist.")
                except Exception as e:
                    print(f"Error deleting {file_path}: {e}")

        # List of files to delete
        files_to_delete = [
            'Backend/Bihar_Distance_L1.xlsx',
            'Backend/Result_Sheet12.xlsx',
            'Backend//Tagging_Sheet_Pre11_leg1.xlsx',
            
        ]

        # Call the function to delete the files
        delete_files(files_to_delete)
        
        
        json_data = json.dumps(data)
        json_object = json.loads(json_data)

        if os.path.exists('ouputPickle.pkl'):
            os.remove('ouputPickle.pkl')

        # open pickle file
        dbfile1 = open('ouputPickle.pkl', 'ab')
    elif scenario_type == "rice_dcp_wheat_dcp":
        message = 'DataFile file is incorrect'
        try:
            USN = pd.ExcelFile('Backend//Data_2.xlsx')
            month = request.form.get('month')        
            year = request.form.get('year')
            scenario_type = request.form.get('type')
            applicable = request.form.get('applicable')
        except Exception as e:
            data = {}
            data['status'] = 0
            data['message'] = message
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)
        input = pd.ExcelFile('Backend//Data_2.xlsx')
        node1 = pd.read_excel(input,sheet_name="A.2 DCP")
        node2 = pd.read_excel(input,sheet_name="A.1 Warehouse")

        dist = [[0 for a in range(len(node2["SW_ID"]))] for b in range(len(node1["WH_ID"]))]
        phi_1 = []
        phi_2 = []
        delta_phi = []
        delta_lambda = []
        R = 6371 

        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)

        for i in node1.index:
            for j in node2.index:
                phi_1=math.radians(node1["WH_Lat"][i])
                phi_2=math.radians(node2["SW_lat"][j])
                delta_phi=math.radians(node2["SW_lat"][j]-node1["WH_Lat"][i])
                delta_lambda=math.radians(node2["SW_Long"][j]-node1["WH_Long"][i])
                x=math.sin(delta_phi / 2.0) ** 2 + math.cos(phi_1) * math.cos(phi_2) * math.sin(delta_lambda / 2.0) ** 2
                y=2 * math.atan2(math.sqrt(x), math.sqrt(1 - x))
                dist[i][j]=R*y
                
       
        DCP = pd.read_excel(USN, sheet_name='A.2 DCP', index_col=None)
        WH = pd.read_excel(USN, sheet_name='A.1 Warehouse', index_col=None)

        if 'Rice' in WH.columns:
            WH['Demand_FRice'] = WH['Rice']
            WH['Allocation_Rice'] = WH['Rice']
        if 'Wheat' in WH.columns:
            WH['Demand_Wheat'] = WH['Wheat']
            WH['Allocation_Wheat'] = WH['Wheat']

        DCP['WH_District'] = DCP['WH_District'].apply(lambda x: x.replace(' ', ''))
        WH['SW_District'] = WH['SW_District'].apply(lambda x: x.replace(' ', ''))

        excel_path = "Backend//Distance_Intial_L1.xlsx"
        output_path = "Backend//Distance_Initial_L1_updated.xlsx"
        sheet_name = "BG_BG"
        excel_password = "distf"

        # ---------- Step 1: Get latest optimisation table ---------- #
        conn = connect_to_database()
        cursor = conn.cursor(dictionary=True)

        cursor.execute("""
            SELECT id
            FROM optimised_table_leg1
            WHERE month = %s and year = %s
            ORDER BY last_updated DESC
            LIMIT 1
        """, (month, year))
        opt = cursor.fetchone()

        updates = []
        if opt:
            table_name = f"optimiseddata_leg1_{opt['id']}"
            cursor.execute("SHOW TABLES LIKE %s", (table_name,))
            table_exists = cursor.fetchone()
            if table_exists:
                cursor.execute(f"""
                    SELECT from_id, to_id, new_distance_district, approve_district
                    FROM `{table_name}`
                    WHERE LOWER(approve_district) = 'no'
                """)
                updates = cursor.fetchall()

        cursor.close()
        conn.close()

        if updates:
            # ---------- Step 2: Decrypt Excel ---------- #
            decrypted = io.BytesIO()
            with open(excel_path, "rb") as f:
                office = msoffcrypto.OfficeFile(f)
                office.load_key(password=excel_password)
                office.decrypt(decrypted)

            decrypted.seek(0)

            # ---------- Step 3: Read Excel and Parse All Sheets ---------- #
            xl = pd.ExcelFile(decrypted, engine="openpyxl")
            sheets = {name: xl.parse(name) for name in xl.sheet_names}
            df = sheets[sheet_name]

            df.rename(columns={df.columns[0]: "to_id"}, inplace=True)
            df["to_id"] = df["to_id"].astype(str)
            df.set_index("to_id", inplace=True)

            df.columns = df.columns.astype(str)

            # ---------- Step 4: Intelligent Update + Minimal Append ---------- #
            updated_cells = 0
            appended_routes = 0

            for row in updates:
                from_id = str(row["from_id"])
                to_id = str(row["to_id"])
                new_dist = row.get("new_distance_district")
                if new_dist is not None:
                    try:
                        distance = float(new_dist)
                        if distance > 0:
                            # ---- Ensure ROW exists ---- #
                            if to_id not in df.index:
                                df.loc[to_id] = 0
                                appended_routes += 1

                            # ---- Ensure COLUMN exists ---- #
                            if from_id not in df.columns:
                                df[from_id] = 0
                                appended_routes += 1

                            # ---- Update the specific cell ---- #
                            if df.at[to_id, from_id] != distance:
                                df.at[to_id, from_id] = distance
                                updated_cells += 1
                    except (ValueError, TypeError):
                        pass

            # ---------- Step 5: Save Excel with All Sheets and Encrypt ---------- #
            output_path = "Backend//Distance_Initial_L1_updated.xlsx"
            sheets[sheet_name] = df.reset_index()

            plain_buf = io.BytesIO()
            with pd.ExcelWriter(plain_buf, engine="xlsxwriter") as writer:
                for name, sheet_df in sheets.items():
                    sheet_df.to_excel(writer, sheet_name=name, index=False)
            plain_buf.seek(0)

            file = msoffcrypto.format.ooxml.OOXMLFile(plain_buf)
            with open(output_path, "wb") as f_out:
                file.encrypt(excel_password, f_out)
        else:
            import shutil
            shutil.copy(excel_path, output_path)

        
        

        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)

        
        model = LpProblem('Supply-Demand-Problem', LpMinimize)

        Variable3 = []
        
        for i in range(len(DCP['WH_ID'])):
            for j in range(len(WH['SW_ID'])):
                Variable3.append(str(DCP['WH_ID'][i]) + '_'
                                 + str(DCP['WH_District'][i]) + '_'
                                 + str(WH['SW_ID'][j]) + '_'
                                 + str(WH['SW_District'][j]) + f'_FRice_{i}_{j}')
                                 
        Variable4 = []
        for i in range(len(DCP['WH_ID'])):
            for j in range(len(WH['SW_ID'])):
                Variable4.append(str(DCP['WH_ID'][i]) + '_'
                                 + str(DCP['WH_District'][i]) + '_'
                                 + str(WH['SW_ID'][j]) + '_'
                                 + str(WH['SW_District'][j]) + f'_Wheat_{i}_{j}')

        # Variables for Wheat from lEVEL2 TO FPS

        DV_Variables3 = LpVariable.matrix('X', Variable3, cat='float',
                lowBound=0)
        Allocation3 = np.array(DV_Variables3).reshape(len(DCP['WH_ID']),
                len(WH['SW_ID']))
                
        
                
        DV_Variables4 = LpVariable.matrix('Y', Variable4, cat='float',
                lowBound=0)
        Allocation4 = np.array(DV_Variables4).reshape(len(DCP['WH_ID']),
                len(WH['SW_ID']))

        
        
        State_Riceprocurement = {}
        for i in range(len(DCP["WH_District"])):
            District_Name = DCP["WH_District"][i]
            if District_Name not in State_Riceprocurement:
                State_Riceprocurement[District_Name] = float(DCP["Procurement Rice"][i])
            else:
                State_Riceprocurement[District_Name] += float(DCP["Procurement Rice"][i])
           

        District_DemandRice = {}
        for i in range(len(WH["SW_District"])):
            District_Name_FPS = WH["SW_District"][i]
            if District_Name_FPS not in District_DemandRice:
                District_DemandRice[District_Name_FPS] = float(WH["Demand_FRice"][i])
            else:
                District_DemandRice[District_Name_FPS] += float(WH["Demand_FRice"][i]) 
        
        District_Name = []
        District_Name2=[]
        District_Name = [i for i in District_DemandRice if i not in State_Riceprocurement]
        District_Name4 = [i for i in State_Riceprocurement if i not in District_DemandRice]
        District_Name2 = [i for i in District_DemandRice if i in State_Riceprocurement and District_DemandRice[i] >= State_Riceprocurement[i]]
        District_Name_1 = {}
        District_Name_1['District_Name_All'] = District_Name + District_Name2
        District_Name3 = [i for i in District_DemandRice if i in State_Riceprocurement and District_DemandRice[i] <= State_Riceprocurement[i]]
        
        
        name1 = []
        lst1 = []
        for j in range(len(DV_Variables3)):
            name1 = str(DV_Variables3[j])
            lst1 = name1.split("_")
            if lst1[2] in District_Name3 and lst1[4] in District_Name3 and lst1[2]!=lst1[4]:
                model+=DV_Variables3[j]==0
                #print(DV_Variables1[j]==0)
                
        
        
                
        State_Wheatprocurement = {}
        for i in range(len(DCP["WH_District"])):
            District_Name = DCP["WH_District"][i]
            if District_Name not in State_Wheatprocurement:
                State_Wheatprocurement[District_Name] = float(DCP["Procurement Wheat"][i])
            else:
                State_Wheatprocurement[District_Name] += float(DCP["Procurement Wheat"][i])
                
        District_DemandWheat = {}
        for i in range(len(WH["SW_District"])):
            District_Name_FPS = WH["SW_District"][i]
            if District_Name_FPS not in District_DemandWheat :
                District_DemandWheat [District_Name_FPS] = float(WH["Demand_Wheat"][i])
            else:
                District_DemandWheat [District_Name_FPS] += float(WH["Demand_Wheat"][i])
                 
                
        District_Name_wheat = []
        District_Name2_wheat=[]
        District_Name_wheat= [i for i in District_DemandWheat if i not in State_Wheatprocurement]
        District_Name4_wheat = [i for i in State_Wheatprocurement if i not in District_DemandWheat]
        District_Name2_wheat = [i for i in District_DemandWheat if i in State_Wheatprocurement and District_DemandWheat[i] >= State_Wheatprocurement[i]]
        District_Name_1_wheat = {}
        District_Name_1_wheat['District_Name_All'] = District_Name_wheat + District_Name2_wheat
        District_Name3_wheat = [i for i in District_DemandWheat if i in State_Wheatprocurement and District_DemandWheat[i] <= State_Wheatprocurement[i]]
        
        
        name5 = []
        lst5 = []
        for j in range(len(DV_Variables4)):
            name5 = str(DV_Variables4[j])
            lst5 = name5.split("_")
            if lst5[2] in District_Name3_wheat and lst5[4] in District_Name3_wheat and lst5[2]!=lst5[4]:
                model+=DV_Variables4[j]==0
                #print(DV_Variables1[j]==0)
                

        allCombination3 = []
        allCombination4 = []

        for i in range(len(dist)):
            for j in range(len(WH['SW_ID'])):
                allCombination3.append(Allocation3[i][j] * dist[i][j])
                
        for i in range(len(dist)):
            for j in range(len(WH['SW_ID'])):
                allCombination4.append(Allocation4[i][j] * dist[i][j])
                
                

        model += lpSum(allCombination3 + allCombination4)
        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)

        # Demand Constraints for Wheat

        for i in range(len(WH['SW_ID'])):
            model += (lpSum(Allocation3[j][i] for j in range(len(DCP['WH_ID'
                           ]))) >= WH['Demand_Rice'][i])                  
                           
        for i in range(len(WH['SW_ID'])):
            model += (lpSum(Allocation4[j][i] for j in range(len(DCP['WH_ID'
                           ]))) >= WH['Demand_Wheat'][i])
        
        for i in range(len(DCP['WH_ID'])):
            model += ((lpSum(Allocation3[i][j] for j in range(len(WH['SW_ID'
                           ]))))  <= DCP['Procurement Rice'][i])
        
        for i in range(len(DCP['WH_ID'])):
            model += ((lpSum(Allocation4[i][j] for j in range(len(WH['SW_ID'
                           ]))))  <= DCP['Procurement Wheat'][i])


      
        model.solve(COIN_CMD(path=get_cbc_path(),msg=0,gapRel=0.03,timeLimit=600))
        
        status = LpStatus[model.status]

        if status != "Optimal":
            print("Optimization failed:", status)

            data = {
                "status": 0,
                "message": "Infeasible or Unbounded Solution"
            }

            return json.dumps(data, indent=1)

        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)

        
        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)        
        
        

        Original_Cost = 100000000
        total = Original_Cost

        data = {}
        

        
        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)

        Output_File = open('Backend//Inter_District1_leg1.csv', 'w')
        for v in model.variables():
            if v.value() > 0:
                Output_File.write(v.name + '\t' + str(v.value()) + '\n')

        Output_File = open('Backend//Inter_District1_leg1.csv', 'w')
        for v in model.variables():
            if v.value() > 0:
                Output_File.write(v.name + '\t' + str(v.value()) + '\n')

        df9 = pd.read_csv('Backend//Inter_District1_leg1.csv',header=None)

        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)        

        df9.columns = ['Tagging']
        df9[[
            'Var',
            'WH_ID',
            'W_D',
            'SW_ID',
            'SW_D',
            'commodity_Value',
            ]] = df9[df9.columns[0]].str.split('_', n=5, expand=True)
        del df9[df9.columns[0]]
        df9[['commodity', 'Values']] = df9['commodity_Value'].str.split('\\t', n=1, expand=True)
        del df9['commodity_Value']
        df9['commodity'] = df9['commodity'].str.split('_').str[0]
        
        df9 = df9.drop(np.where(df9['commodity'] == 'Wheat1')[0])
        def convert_to_numeric(value):
            try:
                return pd.to_numeric(value)
            except ValueError:
                return value
        
        
        df9['WH_ID'] = df9['WH_ID'].apply(convert_to_numeric)
        df9['SW_ID'] = df9['SW_ID'].apply(convert_to_numeric)
        
        df9.to_excel('Backend//Tagging_Sheet_Pre_leg1.xlsx', sheet_name='BG_FPS')
        df31 = pd.read_excel('Backend//Tagging_Sheet_Pre_leg1.xlsx')
         
        USN = pd.ExcelFile('Backend//Data_2.xlsx')
        WH = pd.read_excel(USN, sheet_name='A.1 Warehouse', index_col=None)
        DCP = pd.read_excel(USN, sheet_name='A.2 DCP', index_col=None)        # Convert to object type, adjust as needed
        

        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)

        df4 = pd.merge(df31, DCP, on='WH_ID', how='inner')
        #df4 = pd.merge(df31, FCI, on='WH_ID', how='inner')
        df4 = df4[[
            'WH_ID',
            'WH_Name',
            'WH_District',
            'WH_Lat',
            'WH_Long',
            'SW_ID',
            'commodity',
            'Values',
            ]]
        df4 = pd.merge(df4, WH, on='SW_ID', how='inner')
        df51 = df4[[
            'WH_ID',
            'WH_Name',
            'WH_District',
            'WH_Lat',
            'WH_Long',
            'SW_ID',
            'SW_Name',
            'SW_District',
            'SW_lat',
            'SW_Long',
             'commodity',
            'Values',
            ]]
        df51.insert(0, 'Scenario', 'Optimized')
        df51.insert(1, 'From', 'DCP')
        df51.insert(2, 'From_State', 'Bihar')
        df51.insert(7, 'To', 'TPDS')
        df51.insert(8, 'To_State', 'Bihar')
        
        df51.rename(columns={
            'WH_ID': 'From_ID',
            'WH_Name': 'From_Name',
            'WH_Lat': 'From_Lat',
            'WH_Long': 'From_Long',
            }, inplace=True)
        df51.rename(columns={
            'SW_ID': 'To_ID',
            'SW_Name': 'To_Name',
            'SW_lat': 'To_Lat',
            'SW_Long': 'To_Long',
            'Values':'quantity',
            }, inplace=True)
        df51.rename(columns={'WH_District': 'From_District',
                   'SW_District': 'To_District'}, inplace=True)
        df51 = df51.loc[:, [
            'Scenario',
            'From',
            'From_State',
            'From_District',
            'From_ID',
            'From_Name',
            'From_Lat',
            'From_Long',
            'To',
            'To_ID',
            'To_Name',
            'To_State',
            'To_District',
            'To_Lat',
            'To_Long',
            'commodity',
            'quantity',
            ]]
            
        def convert_to_numeric(value):
            try:
                return pd.to_numeric(value)
            except ValueError:
                return value
                
        
        df51['From_ID'] = df51['From_ID'].apply(convert_to_numeric)
        df51['To_ID'] = df51['To_ID'].apply(convert_to_numeric)   
        
        df51.to_excel('Backend//Tagging_Sheet_Pre11_leg1.xlsx', sheet_name='BG_FPS1')
        data1 = pd.ExcelFile("Backend//Tagging_Sheet_Pre11_leg1.xlsx")
        df5 = pd.read_excel(data1,sheet_name="BG_FPS1")
        data1.close()
        
        # ==========================================================
        # READ MASTER DATA
        # ==========================================================
        input_file = pd.ExcelFile('Backend//Data_2.xlsx')

        # Warehouse Sheet
        node1 = pd.read_excel(input_file, sheet_name="A.1 Warehouse")

        node1['SW_ID'] = node1['SW_ID'].astype(str).str.strip()

        node1['Lat_Long_r'] = (
            node1[['SW_lat', 'SW_Long']]
            .astype(float)
            .round(3)
            .astype(str)
            .agg(','.join, axis=1)
        )

        # FCI Sheet
        node2 = pd.read_excel(input_file, sheet_name="A.2 FCI")

        node2['WH_ID'] = node2['WH_ID'].astype(str).str.strip()

        node2['Lat_Long_r'] = (
            node2[['WH_Lat', 'WH_Long']]
            .astype(float)
            .round(3)
            .astype(str)
            .agg(','.join, axis=1)
        )

      
        updated_excel_path = 'Backend//Distance_Initial_L1_updated.xlsx'
        ref_excel_path = updated_excel_path if os.path.exists(updated_excel_path) else 'Backend//Distance_Intial_L1.xlsx'
        DistanceBing = read_protected_excel(ref_excel_path, 'distf', sheet_name='DCP_BG')
        Warehouse = read_protected_excel(ref_excel_path, 'distf', sheet_name='Warehouse')
        DCP = read_protected_excel(ref_excel_path, 'distf', sheet_name='DCP')
        
        
                # ==========================================================
        # STANDARDIZE IDS
        # ==========================================================
        Warehouse['SW_ID'] = Warehouse['SW_ID'].astype(str).str.strip()
        FCI['WH_ID'] = FCI['WH_ID'].astype(str).str.strip()

        # ==========================================================
        # ROUND LAT LONG IN DISTANCE FILE
        # ==========================================================
        Warehouse['Lat_Long_r'] = (
            Warehouse['Lat_Long']
            .astype(str)
            .str.split(',', expand=True)
            .astype(float)
            .round(3)
            .astype(str)
            .agg(','.join, axis=1)
        )

        FCI['Lat_Long_r'] = (
            FCI['Lat_Long']
            .astype(str)
            .str.split(',', expand=True)
            .astype(float)
            .round(3)
            .astype(str)
            .agg(','.join, axis=1)
        )

        # ==========================================================
        # FIND WAREHOUSES WITH CHANGED LAT LONG
        # ==========================================================
        War = pd.merge(
            node1[['SW_ID', 'Lat_Long_r']],
            Warehouse[['SW_ID', 'Lat_Long_r']],
            on='SW_ID',
            how='inner',
            suffixes=('_master', '_distance')
        )

        Warehouse_ID = War.loc[
            War['Lat_Long_r_master'] != War['Lat_Long_r_distance'],
            'SW_ID'
        ].astype(str).unique()

        print("Warehouse IDs to remove:", len(Warehouse_ID))

        # ==========================================================
        # FIND FCI WITH CHANGED LAT LONG
        # ==========================================================
        FPS1 = pd.merge(
            node2[['WH_ID', 'Lat_Long_r']],
            FCI[['WH_ID', 'Lat_Long_r']],
            on='WH_ID',
            how='inner',
            suffixes=('_master', '_distance')
        )

        FPS_ID = FPS1.loc[
            FPS1['Lat_Long_r_master'] != FPS1['Lat_Long_r_distance'],
            'WH_ID'
        ].astype(str).unique()

        print("FCI IDs to remove:", len(FPS_ID))

        # ==========================================================
        # REMOVE FROM DISTANCE MATRIX
        # ==========================================================

        # Convert all column names to string
        DistanceBing.columns = DistanceBing.columns.astype(str)

        # If first column contains row IDs, convert to string
        DistanceBing.iloc[:, 0] = DistanceBing.iloc[:, 0].astype(str)

        # Remove warehouse columns
        Distance1 = DistanceBing.drop(
            columns=[col for col in DistanceBing.columns if col in Warehouse_ID],
            errors='ignore'
        )

        # Remove FCI rows
        Distance2 = Distance1[
            ~Distance1.iloc[:, 0].isin(FPS_ID)
        ]

        # ==========================================================
        # SAVE OUTPUT
        # ==========================================================
        with pd.ExcelWriter(
            'Backend//Bihar_Distance_L1.xlsx',
            engine='openpyxl'
        ) as writer:

            Distance2.to_excel(
                writer,
                sheet_name='BG_BG',
                index=False
            )

        print("Distance matrix updated successfully.")
        print("Final Shape:", Distance2.shape)
        
        Cost=pd.ExcelFile('Backend//Bihar_Distance_L1.xlsx')
        BG_BG = pd.read_excel(Cost,sheet_name="BG_BG")
        Cost.close()
        
        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)        

        Distance_BG_BG = {}
        column_list_BG_BG = list(BG_BG.columns)
        #print(column_list_BG_BG)
        row_list_BG_BG = list(BG_BG.iloc[:, 0])
        #print(row_list_BG_BG )  
        for ind in df5.index:
            from_code= df5['From_ID'][ind] 
            to_code = df5['To_ID'][ind]
            if to_code in row_list_BG_BG and from_code in column_list_BG_BG:
                index_i = row_list_BG_BG.index(to_code)
                index_j = column_list_BG_BG.index(from_code)
                key = str(to_code) + "_" + str(from_code)
                Distance_BG_BG[key]= BG_BG.iloc[index_i , index_j]
                
            
        #df5["Tagging"]=df5['To_ID']+ '_' + df5['From_ID']
        df5["Tagging"] = df5['To_ID'].astype(str) + '_' + df5['From_ID'].astype(str)
        df5['Distance'] = df5['Tagging'].map(Distance_BG_BG)
        df5 = df5.replace('',pd.NaT).fillna('shallu')
        d5=df5.loc[df5['Distance'] == "shallu"]
        df5.to_excel('Backend//Result_Sheet12.xlsx',
                         sheet_name='Warehouse_FPS')

        
# ----------------------------------------------------------------------------------------------------------------------------------------------
        
        # -------------------- READ INPUT --------------------
        Result_Sheet1 = pd.ExcelFile("Backend//Result_Sheet12.xlsx")
        df6 = pd.read_excel(Result_Sheet1, sheet_name="Warehouse_FPS")
        Result_Sheet1.close()

        df7 = df6.loc[df6['Distance'] == "shallu"].reset_index(drop=True)

        # -------------------- API Details --------------------
        auth_url = 'https://kerala.pmgatishakti.gov.in/DFPD/authenticate'
        distance_url = 'https://kerala.pmgatishakti.gov.in/PMGatishaktiApiService/dfpdapi/roaddistance'

        auth_payload = {
            "username": "DFPD_C",
            "password": "W9Vtb8WKkt3"
        }

        FILE_PATH = 'distanceIndent.json'

        # -------------------- Get Token --------------------
        def get_token():
            try:
                response = requests.post(auth_url, json=auth_payload, timeout=240)
                if response.status_code == 200:
                    return response.json().get('token')
                return None
            except requests.exceptions.RequestException as e:
                print("Auth API Error:", e)
                raise Exception("PMGatiShakti Authentication Service is currently unavailable. Please check your internet connection or try again later.")

        # -------------------- Batch API --------------------
        def process_batch(df_batch, token):
            headers = {'Authorization': f'Bearer {token}'}

            data = {
                "parameter": [{
                    "src_lng": row["From_Long"],
                    "src_lat": row["From_Lat"],
                    "dest_lng": row["To_Long"],
                    "dest_lat": row["To_Lat"]
                } for _, row in df_batch.iterrows()]
            }

            try:
                with open(FILE_PATH, 'w') as f:
                    json.dump(data, f, indent=4)

                with open(FILE_PATH, 'rb') as f:
                    files = {'LatsLongsFile': f}
                    response = requests.post(distance_url, headers=headers, files=files, timeout=240)

                return response
            except requests.exceptions.RequestException as e:
                print("Batch API Error:", e)
                raise Exception("PMGatiShakti Distance Service is currently unavailable. Please check your internet connection or try again later.")
            except Exception as e:
                print("Batch API Error:", e)
                return None

        # -------------------- Single Row API --------------------
        def process_single_row(row, token):
            headers = {'Authorization': f'Bearer {token}'}

            data = {
                "parameter": [{
                    "src_lng": row["From_Long"],
                    "src_lat": row["From_Lat"],
                    "dest_lng": row["To_Long"],
                    "dest_lat": row["To_Lat"]
                }]
            }

            try:
                with open(FILE_PATH, 'w') as f:
                    json.dump(data, f, indent=4)

                with open(FILE_PATH, 'rb') as f:
                    files = {'LatsLongsFile': f}
                    response = requests.post(distance_url, headers=headers, files=files, timeout=240)

                if response.status_code != 200:
                    return 0

                res_json = response.json()
                api_data = res_json.get("data", [])

                if len(api_data) == 0:
                    return 0

                distance = api_data[0].get("distance")

                if isinstance(distance, (int, float)):
                    return distance

                return 0
            except requests.exceptions.RequestException as e:
                print("Row API Error (Connection):", e)
                return "CONNECTION_ERROR"
            except Exception as e:
                print("Row API Error:", e)
                return 0

        # ----------------------------------------------------------------------------------------------------------------------------------------------
        # -------------------- MAIN PROCESS --------------------

        batch_size = 1000
        total_rows = len(df7)
        num_batches = (total_rows + batch_size - 1) // batch_size

        dist3 = []

        for batch_num in range(num_batches):
            print(f"Processing batch {batch_num+1}/{num_batches}")

            start_idx = batch_num * batch_size
            end_idx = min((batch_num + 1) * batch_size, total_rows)
            df_batch = df7.iloc[start_idx:end_idx]

            token = get_token()
            if not token:
                data_err = {"status": 0, "message": "Failed to retrieve PMGatiShakti token."}
                return json.dumps(data_err, indent=1)

            response = process_batch(df_batch, token)

            fallback_required = False

            if not response or response.status_code != 200:
                fallback_required = True
            else:
                try:
                    response_json = response.json()
                    api_data = response_json.get("data", [])

                    if len(api_data) != len(df_batch):
                        fallback_required = True
                    else:
                        for row_data in api_data:
                            distance = row_data.get("distance")
                            if not isinstance(distance, (int, float)):
                                fallback_required = True
                                break

                except Exception:
                    fallback_required = True

            # ---------------- FALLBACK ----------------
            if fallback_required:
                print(f"Batch {batch_num+1} failed -> switching to row-wise")

                for _, row in df_batch.iterrows():
                    distance = process_single_row(row, token)
                    if distance == "CONNECTION_ERROR":
                        data_err = {"status": 0, "message": "PMGatiShakti API is currently unavailable or there is an internet connection issue. Please check your connection and try again."}
                        return json.dumps(data_err, indent=1)

                    if distance == 0:
                        print(f"Distance set to 0 for From {row['From_ID']} -> To {row['To_ID']}")

                    dist3.append(distance)

            # ---------------- NORMAL ----------------
            else:
                for row_data in api_data:
                    dist3.append(row_data.get("distance"))

        

        # -------------------- UPDATE DATA --------------------
        df7["Distance"] = dist3

        df9 = df6.loc[df6['Distance'] != "shallu"]

        columns = [
            'Scenario','From','From_State','From_District','From_ID','From_Name',
            'From_Lat','From_Long','To','To_ID','To_Name','To_State','To_District',
            'To_Lat','To_Long','commodity','quantity','Distance'
        ]

        df9 = df9[columns]
        df7 = df7[columns]

        df10 = pd.concat([df9, df7], ignore_index=True)

        # -------------------- FINAL RESULT --------------------
        result = (df10['quantity'] * df10['Distance']).sum()

        print("Total Result:", result)

        # -------------------- SAVE OUTPUT --------------------
        df10.to_excel('Backend//Result_Sheet_leg1.xlsx', sheet_name='Warehouse_FPS', index=False)

        print("Process Completed Successfully")
# ----------------------------------------------------------------------------------------------------------------------------------------------              
        Total_Demand=  float(WH['Demand_Wheat'].sum()) + float(WH['Demand_Rice'].sum())        

        data ={}        
        
        data["Scenario"]="Inter"
        data["Scenario_Baseline"] = "Baseline"
        
        data["WH_Used"] = df5['From_ID'].nunique()
        data["WH_Used_Baseline"] = "392"
        
        data["FPS_Used"] = df5['To_ID'].nunique()
        data["FPS_Used_Baseline"] = "459"
        
        data['Demand'] = Total_Demand
        data['Demand_Baseline'] = "46,05,917.05"
        
        
        
        data['Total_QKM'] = float(result)
        data['Total_QKM_Baseline'] ="15,51,98,041.05"
        
        data['Average_Distance'] = float(round(result, 2)) / Total_Demand
        data['Average_Distance_Baseline'] = "33.69"

        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)                     
        
        
        save_to_database_leg1(month, year, applicable, scenario_type)
        save_monthly_data_leg1(month, year, float(result))
        
        
        def delete_files(file_paths):
            for file_path in file_paths:
                try:
                    if os.path.exists(file_path):  # Check if the file exists
                        os.remove(file_path)  # Delete the file
                        #print(f"{file_path} has been deleted.")
                    else:
                        print(f"{file_path} does not exist.")
                except Exception as e:
                    print(f"Error deleting {file_path}: {e}")

        # List of files to delete
        files_to_delete = [
            'Backend/Bihar_Distance_L1.xlsx',
            'Backend/Result_Sheet12.xlsx',
            'Backend//Tagging_Sheet_Pre11_leg1.xlsx',
            
        ]

        # Call the function to delete the files
        delete_files(files_to_delete)

        
        json_data = json.dumps(data)
        json_object = json.loads(json_data)

        if os.path.exists('ouputPickle.pkl'):
            os.remove('ouputPickle.pkl')

        # open pickle file
        dbfile1 = open('ouputPickle.pkl', 'ab')

    elif scenario_type == "rice_dcp_wheat_fci":
        message = 'DataFile file is incorrect'
        try:
            USN = pd.ExcelFile('Backend//Data_2.xlsx')
            month = request.form.get('month')        
            year = request.form.get('year')
            scenario_type = request.form.get('type')
            applicable = request.form.get('applicable')
        except Exception as e:
            data = {}
            data['status'] = 0
            data['message'] = message
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)
        input = pd.ExcelFile('Backend//Data_2.xlsx')
        node1 = pd.read_excel(input,sheet_name="A.2 FCI")
        node2 = pd.read_excel(input,sheet_name="A.1 Warehouse")
        node3 = pd.read_excel(input,sheet_name="A.2 DCP")

        dist = [[0 for a in range(len(node2["SW_ID"]))] for b in range(len(node1["WH_ID"]))]
        phi_1 = []
        phi_2 = []
        delta_phi = []
        delta_lambda = []
        R = 6371 

        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)

        for i in node1.index:
            for j in node2.index:
                phi_1=math.radians(node1["WH_Lat"][i])
                phi_2=math.radians(node2["SW_lat"][j])
                delta_phi=math.radians(node2["SW_lat"][j]-node1["WH_Lat"][i])
                delta_lambda=math.radians(node2["SW_Long"][j]-node1["WH_Long"][i])
                x=math.sin(delta_phi / 2.0) ** 2 + math.cos(phi_1) * math.cos(phi_2) * math.sin(delta_lambda / 2.0) ** 2
                y=2 * math.atan2(math.sqrt(x), math.sqrt(1 - x))
                dist[i][j]=R*y
                
       
       


        dist1 = [[0 for a in range(len(node2["SW_ID"]))] for b in range(len(node3["WH_ID"]))]
        phi_11 = []
        phi_21 = []
        delta_phi1 = []
        delta_lambda1 = []
        R = 6371 

        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)

        for i in node3.index:
            for j in node2.index:
                phi_11=math.radians(node3["WH_Lat"][i])
                phi_21=math.radians(node2["SW_lat"][j])
                delta_phi1=math.radians(node2["SW_lat"][j]-node3["WH_Lat"][i])
                delta_lambda1=math.radians(node2["SW_Long"][j]-node3["WH_Long"][i])
                x=math.sin(delta_phi1 / 2.0) ** 2 + math.cos(phi_11) * math.cos(phi_21) * math.sin(delta_lambda1 / 2.0) ** 2
                y=2 * math.atan2(math.sqrt(x), math.sqrt(1 - x))
                dist1[i][j]=R*y
                
        
        
       
        FCI = pd.read_excel(USN, sheet_name='A.2 FCI', index_col=None)
        WH = pd.read_excel(USN, sheet_name='A.1 Warehouse', index_col=None)
        DCP = pd.read_excel(USN, sheet_name='A.2 DCP', index_col=None)

        if 'Rice' in WH.columns:
            WH['Demand_Rice'] = WH['Rice']
            WH['Allocation_Rice'] = WH['Rice']
        if 'Wheat' in WH.columns:
            WH['Demand_Wheat'] = WH['Wheat']
            WH['Allocation_Wheat'] = WH['Wheat']

        FCI['WH_District'] = FCI['WH_District'].apply(lambda x: x.replace(' ', ''))
        WH['SW_District'] = WH['SW_District'].apply(lambda x: x.replace(' ', ''))
        DCP['WH_District'] = DCP['WH_District'].apply(lambda x: x.replace(' ', ''))
        
        
        excel_path = "Backend//Distance_Intial_L1.xlsx"
        output_path = "Backend//Distance_Initial_L1_updated.xlsx"
        sheet_name = "BG_BG"
        excel_password = "distf"

        # ---------- Step 1: Get latest optimisation table ---------- #
        conn = connect_to_database()
        cursor = conn.cursor(dictionary=True)

        cursor.execute("""
            SELECT id
            FROM optimised_table_leg1
            WHERE month = %s and year = %s
            ORDER BY last_updated DESC
            LIMIT 1
        """, (month, year))
        opt = cursor.fetchone()

        updates = []
        if opt:
            table_name = f"optimiseddata_leg1_{opt['id']}"
            cursor.execute("SHOW TABLES LIKE %s", (table_name,))
            table_exists = cursor.fetchone()
            if table_exists:
                cursor.execute(f"""
                    SELECT from_id, to_id, new_distance_district, approve_district
                    FROM `{table_name}`
                    WHERE LOWER(approve_district) = 'no'
                """)
                updates = cursor.fetchall()

        cursor.close()
        conn.close()

        if updates:
            # ---------- Step 2: Decrypt Excel ---------- #
            decrypted = io.BytesIO()
            with open(excel_path, "rb") as f:
                office = msoffcrypto.OfficeFile(f)
                office.load_key(password=excel_password)
                office.decrypt(decrypted)

            decrypted.seek(0)

            # ---------- Step 3: Read Excel and Parse All Sheets ---------- #
            xl = pd.ExcelFile(decrypted, engine="openpyxl")
            sheets = {name: xl.parse(name) for name in xl.sheet_names}
            df = sheets[sheet_name]

            df.rename(columns={df.columns[0]: "to_id"}, inplace=True)
            df["to_id"] = df["to_id"].astype(str)
            df.set_index("to_id", inplace=True)

            df.columns = df.columns.astype(str)

            # ---------- Step 4: Intelligent Update + Minimal Append ---------- #
            updated_cells = 0
            appended_routes = 0

            for row in updates:
                from_id = str(row["from_id"])
                to_id = str(row["to_id"])
                new_dist = row.get("new_distance_district")
                if new_dist is not None:
                    try:
                        distance = float(new_dist)
                        if distance > 0:
                            # ---- Ensure ROW exists ---- #
                            if to_id not in df.index:
                                df.loc[to_id] = 0
                                appended_routes += 1

                            # ---- Ensure COLUMN exists ---- #
                            if from_id not in df.columns:
                                df[from_id] = 0
                                appended_routes += 1

                            # ---- Update the specific cell ---- #
                            if df.at[to_id, from_id] != distance:
                                df.at[to_id, from_id] = distance
                                updated_cells += 1
                    except (ValueError, TypeError):
                        pass

            # ---------- Step 5: Save Excel with All Sheets and Encrypt ---------- #
            output_path = "Backend//Distance_Initial_L1_updated.xlsx"
            sheets[sheet_name] = df.reset_index()

            plain_buf = io.BytesIO()
            with pd.ExcelWriter(plain_buf, engine="xlsxwriter") as writer:
                for name, sheet_df in sheets.items():
                    sheet_df.to_excel(writer, sheet_name=name, index=False)
            plain_buf.seek(0)

            file = msoffcrypto.format.ooxml.OOXMLFile(plain_buf)
            with open(output_path, "wb") as f_out:
                file.encrypt(excel_password, f_out)
        else:
            import shutil
            shutil.copy(excel_path, output_path)
            
        
        model = LpProblem('Supply-Demand-Problem', LpMinimize)

        Variable3 = []
        for i in range(len(DCP['WH_ID'])):
            for j in range(len(WH['SW_ID'])):
                Variable3.append(str(DCP['WH_ID'][i]) + '_'
                                 + str(DCP['WH_District'][i]) + '_'
                                 + str(WH['SW_ID'][j]) + '_'
                                 + str(WH['SW_District'][j]) + f'_Rice_{i}_{j}')
                                 
        Variable4 = []
        for i in range(len(FCI['WH_ID'])):
            for j in range(len(WH['SW_ID'])):
                Variable4.append(str(FCI['WH_ID'][i]) + '_'
                                 + str(FCI['WH_District'][i]) + '_'
                                 + str(WH['SW_ID'][j]) + '_'
                                 + str(WH['SW_District'][j]) + f'_Wheat_{i}_{j}')

        DV_Variables3 = LpVariable.matrix('X', Variable3, cat='float',
                lowBound=0)
        Allocation3 = np.array(DV_Variables3).reshape(len(DCP['WH_ID']),
                len(WH['SW_ID']))
                        
        DV_Variables4 = LpVariable.matrix('Y', Variable4, cat='float',
                lowBound=0)
        Allocation4 = np.array(DV_Variables4).reshape(len(FCI['WH_ID']),
                len(WH['SW_ID']))

        State_Riceprocurement = {}
        for i in range(len(DCP["WH_District"])):
            District_Name = DCP["WH_District"][i]
            if District_Name not in State_Riceprocurement:
                State_Riceprocurement[District_Name] = float(DCP["Procurement Rice"][i])
            else:
                State_Riceprocurement[District_Name] += float(DCP["Procurement Rice"][i])
           

        District_DemandRice = {}
        for i in range(len(WH["SW_District"])):
            District_Name_FPS = WH["SW_District"][i]
            if District_Name_FPS not in District_DemandRice:
                District_DemandRice[District_Name_FPS] = float(WH["Demand_Rice"][i])
            else:
                District_DemandRice[District_Name_FPS] += float(WH["Demand_Rice"][i]) 
        
        District_Name = []
        District_Name2=[]
        District_Name = [i for i in District_DemandRice if i not in State_Riceprocurement]
        District_Name4 = [i for i in State_Riceprocurement if i not in District_DemandRice]
        District_Name2 = [i for i in District_DemandRice if i in State_Riceprocurement and District_DemandRice[i] >= State_Riceprocurement[i]]
        District_Name_1 = {}
        District_Name_1['District_Name_All'] = District_Name + District_Name2
        District_Name3 = [i for i in District_DemandRice if i in State_Riceprocurement and District_DemandRice[i] <= State_Riceprocurement[i]]
        
        
        name1 = []
        lst1 = []
        for j in range(len(DV_Variables3)):
            name1 = str(DV_Variables3[j])
            lst1 = name1.split("_")
            if lst1[2] in District_Name3 and lst1[4] in District_Name3 and lst1[2]!=lst1[4]:
                model+=DV_Variables3[j]==0
                #print(DV_Variables1[j]==0)
                
        
        District_Capacity = {}
        for i in range(len(FCI["WH_District"])):
            District_Name = FCI["WH_District"][i]
            if District_Name not in District_Capacity:
                District_Capacity[District_Name] = float(FCI["Allotment_Wheat"][i])
            else:
                District_Capacity[District_Name] += float(FCI["Allotment_Wheat"][i])
                
        District_DemandWheat = {}
        for i in range(len(WH["SW_District"])):
            District_Name_FPS = WH["SW_District"][i]
            if District_Name_FPS not in District_DemandWheat :
                District_DemandWheat [District_Name_FPS] = float(WH["Demand_Wheat"][i])
            else:
                District_DemandWheat [District_Name_FPS] += float(WH["Demand_Wheat"][i])
                
                
        District_Name_wheat = []
        District_Name2_wheat=[]
        District_Name_wheat= [i for i in District_DemandWheat if i not in District_Capacity]
        District_Name4_wheat = [i for i in District_Capacity if i not in District_Capacity]
        District_Name2_wheat = [i for i in District_DemandWheat if i in District_Capacity and District_DemandWheat[i] >= District_Capacity[i]]
        District_Name_1_wheat = {}
        District_Name_1_wheat['District_Name_All'] = District_Name_wheat + District_Name2_wheat
        District_Name3_wheat = [i for i in District_DemandWheat if i in District_Capacity and District_DemandWheat[i] <= District_Capacity[i]]
        
        
        name5 = []
        lst5 = []
        for j in range(len(DV_Variables4)):
            name5 = str(DV_Variables4[j])
            lst5 = name5.split("_")
            if lst5[2] in District_Name3_wheat and lst5[4] in District_Name3_wheat and lst5[2]!=lst5[4]:
                model+=DV_Variables4[j]==0
                #print(DV_Variables1[j]==0)
                
        
        
      

        allCombination3 = []
        allCombination4 = []

        for i in range(len(dist1)):
            for j in range(len(WH['SW_ID'])):
                allCombination3.append(Allocation3[i][j] * dist1[i][j])
                
        for i in range(len(dist)):
            for j in range(len(WH['SW_ID'])):
                allCombination4.append(Allocation4[i][j] * dist[i][j])
                
                

        model += lpSum(allCombination3 + allCombination4)
        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)

        # Demand Constraints for Wheat

        for i in range(len(WH['SW_ID'])):
            model += (lpSum(Allocation3[j][i] for j in range(len(DCP['WH_ID'
                           ]))) >= WH['Demand_Rice'][i])
                           
        for i in range(len(WH['SW_ID'])):
            model += (lpSum(Allocation4[j][i] for j in range(len(FCI['WH_ID'
                           ]))) >= WH['Demand_Wheat'][i])

        # Supply Constraints for Warehouses

        for i in range(len(DCP['WH_ID'])):
            model += ((lpSum(Allocation3[i][j] for j in range(len(WH['SW_ID'
                           ]))))  <= DCP['Procurement Rice'][i])
                           
        for i in range(len(FCI['WH_ID'])):
            model += ((lpSum(Allocation4[i][j] for j in range(len(WH['SW_ID'
                           ]))))  <= FCI['Allotment_Wheat'][i])

       
        model.solve(COIN_CMD(path=get_cbc_path(),msg=0,gapRel=0.03,timeLimit=600))

        
        status = LpStatus[model.status]

        if status != "Optimal":
            print("Optimization failed:", status)

            data = {
                "status": 0,
                "message": "Infeasible or Unbounded Solution"
            }

            return json.dumps(data, indent=1)

        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)

       
        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)        
        
       

        Original_Cost = 100000000
        total = Original_Cost

        data = {}
        
        
        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)

        Output_File = open('Backend//Inter_District1_leg1.csv', 'w')
        for v in model.variables():
            if v.value() > 0:
                Output_File.write(v.name + '\t' + str(v.value()) + '\n')

        Output_File = open('Backend//Inter_District1_leg1.csv', 'w')
        for v in model.variables():
            if v.value() > 0:
                Output_File.write(v.name + '\t' + str(v.value()) + '\n')

        df9 = pd.read_csv('Backend//Inter_District1_leg1.csv',header=None)

        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)        

        df9.columns = ['Tagging']
        df9[[
            'Var',
            'WH_ID',
            'W_D',
            'SW_ID',
            'SW_D',
            'commodity_Value',
            ]] = df9[df9.columns[0]].str.split('_', n=5, expand=True)
        del df9[df9.columns[0]]
        df9[['commodity', 'Values']] = df9['commodity_Value'].str.split('\\t', n=1, expand=True)
        del df9['commodity_Value']
        df9['commodity'] = df9['commodity'].str.split('_').str[0]
        
        df9 = df9.drop(np.where(df9['commodity'] == 'Wheat1')[0])
        def convert_to_numeric(value):
            try:
                return pd.to_numeric(value)
            except ValueError:
                return value
        
        
        df9['WH_ID'] = df9['WH_ID'].apply(convert_to_numeric)
        df9['SW_ID'] = df9['SW_ID'].apply(convert_to_numeric)
        
        df9.to_excel('Backend//Tagging_Sheet_Pre_leg1.xlsx', sheet_name='BG_FPS')
        df31 = pd.read_excel('Backend//Tagging_Sheet_Pre_leg1.xlsx')
        
        USN = pd.ExcelFile('Backend//Data_2.xlsx')
        WH = pd.read_excel(USN, sheet_name='A.1 Warehouse', index_col=None)
        FCI = pd.read_excel(USN, sheet_name='A.2 FCI', index_col=None)        # Convert to object type, adjust as needed
        DCP = pd.read_excel(USN, sheet_name='A.2 DCP', index_col=None)  
        
        columns_to_include = ["WH_District","WH_Name","WH_ID",	"Type of WH",	"WH_Lat",	"WH_Long"]
        df1_selected = FCI[columns_to_include]
        df2_selected = DCP[columns_to_include]
        
        FCI = pd.concat([df1_selected, df2_selected], ignore_index=True)

        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)
        


        df4 = pd.merge(df31, FCI, on='WH_ID', how='inner')
        #df4 = pd.merge(df31, FCI, on='WH_ID', how='inner')
        df4 = df4[[
            'WH_ID',
            'WH_Name',
            'WH_District',
            'WH_Lat',
            'WH_Long',
            'Type of WH',
            'SW_ID',
            'commodity',
            'Values',
            ]]
        df4 = pd.merge(df4, WH, on='SW_ID', how='inner')
        df51 = df4[[
            'WH_ID',
            'WH_Name',
            'WH_District',
            'WH_Lat',
            'WH_Long',
            'Type of WH',
            'SW_ID',
            'SW_Name',
            'SW_District',
            'SW_lat',
            'SW_Long',
            'commodity',
            'Values',
            ]]
        df51.insert(0, 'Scenario', 'Optimized')
        df51.insert(2, 'From_State', 'Bihar')
        df51.insert(7, 'To', 'TPDS')
        df51.insert(8, 'To_State', 'Bihar')
        
        df51.rename(columns={
            'WH_ID': 'From_ID',
            'WH_Name': 'From_Name',
            'WH_Lat': 'From_Lat',
            'Type of WH': 'From',
            'WH_Long': 'From_Long',
            }, inplace=True)
        df51.rename(columns={
            'SW_ID': 'To_ID',
            'SW_Name': 'To_Name',
            'SW_lat': 'To_Lat',
            'SW_Long': 'To_Long',
            'Values':'quantity',
            }, inplace=True)
        df51.rename(columns={'WH_District': 'From_District',
                   'SW_District': 'To_District'}, inplace=True)
        df51 = df51.loc[:, [
            'Scenario',
            'From',
            'From_State',
            'From_District',
            'From_ID',
            'From_Name',
            'From_Lat',
            'From_Long',
            'To',
            'To_ID',
            'To_Name',
            'To_State',
            'To_District',
            'To_Lat',
            'To_Long',
            'commodity',
            'quantity',
            ]]
            
        def convert_to_numeric(value):
            try:
                return pd.to_numeric(value)
            except ValueError:
                return value
                
        
        df51['From_ID'] = df51['From_ID'].apply(convert_to_numeric)
        df51['To_ID'] = df51['To_ID'].apply(convert_to_numeric)   
        
        df51.to_excel('Backend//Tagging_Sheet_Pre11_leg1.xlsx', sheet_name='BG_FPS1')
        data1 = pd.ExcelFile("Backend//Tagging_Sheet_Pre11_leg1.xlsx")
        df5 = pd.read_excel(data1,sheet_name="BG_FPS1")
        data1.close()
        
        
        
        # ==========================================================
        # READ MASTER DATA
        # ==========================================================
        input_file = pd.ExcelFile('Backend//Data_2.xlsx')

        # Warehouse Sheet
        node1 = pd.read_excel(input_file, sheet_name="A.1 Warehouse")

        node1['SW_ID'] = node1['SW_ID'].astype(str).str.strip()

        node1['Lat_Long_r'] = (
            node1[['SW_lat', 'SW_Long']]
            .astype(float)
            .round(3)
            .astype(str)
            .agg(','.join, axis=1)
        )

        # FCI Sheet
        node4 = pd.read_excel(input,sheet_name="A.2 FCI")
        node3 = pd.read_excel(input,sheet_name="A.2 DCP")
        columns_to_include = ["WH_District","WH_Name","WH_ID",	"Type of WH",	"WH_Lat",	"WH_Long"]
        df1_selected = node4[columns_to_include]
        df2_selected = node3[columns_to_include]
        
        node2 = pd.concat([df1_selected, df2_selected], ignore_index=True)

        node2['WH_ID'] = node2['WH_ID'].astype(str).str.strip()

        node2['Lat_Long_r'] = (
            node2[['WH_Lat', 'WH_Long']]
            .astype(float)
            .round(3)
            .astype(str)
            .agg(','.join, axis=1)
        )

       
        updated_excel_path = 'Backend//Distance_Initial_L1_updated.xlsx'
        ref_excel_path = updated_excel_path if os.path.exists(updated_excel_path) else 'Backend//Distance_Intial_L1.xlsx'
        DistanceBing = read_protected_excel(ref_excel_path, 'distf', sheet_name='BG_BG')
        Warehouse = read_protected_excel(ref_excel_path, 'distf', sheet_name='Warehouse')
        FCI = read_protected_excel(ref_excel_path, 'distf', sheet_name='FCI')
        
                # ==========================================================
        # STANDARDIZE IDS
        # ==========================================================
        Warehouse['SW_ID'] = Warehouse['SW_ID'].astype(str).str.strip()
        FCI['WH_ID'] = FCI['WH_ID'].astype(str).str.strip()

        # ==========================================================
        # ROUND LAT LONG IN DISTANCE FILE
        # ==========================================================
        Warehouse['Lat_Long_r'] = (
            Warehouse['Lat_Long']
            .astype(str)
            .str.split(',', expand=True)
            .astype(float)
            .round(3)
            .astype(str)
            .agg(','.join, axis=1)
        )

        FCI['Lat_Long_r'] = (
            FCI['Lat_Long']
            .astype(str)
            .str.split(',', expand=True)
            .astype(float)
            .round(3)
            .astype(str)
            .agg(','.join, axis=1)
        )

        # ==========================================================
        # FIND WAREHOUSES WITH CHANGED LAT LONG
        # ==========================================================
        War = pd.merge(
            node1[['SW_ID', 'Lat_Long_r']],
            Warehouse[['SW_ID', 'Lat_Long_r']],
            on='SW_ID',
            how='inner',
            suffixes=('_master', '_distance')
        )

        Warehouse_ID = War.loc[
            War['Lat_Long_r_master'] != War['Lat_Long_r_distance'],
            'SW_ID'
        ].astype(str).unique()

        print("Warehouse IDs to remove:", len(Warehouse_ID))

        # ==========================================================
        # FIND FCI WITH CHANGED LAT LONG
        # ==========================================================
        FPS1 = pd.merge(
            node2[['WH_ID', 'Lat_Long_r']],
            FCI[['WH_ID', 'Lat_Long_r']],
            on='WH_ID',
            how='inner',
            suffixes=('_master', '_distance')
        )

        FPS_ID = FPS1.loc[
            FPS1['Lat_Long_r_master'] != FPS1['Lat_Long_r_distance'],
            'WH_ID'
        ].astype(str).unique()

        print("FCI IDs to remove:", len(FPS_ID))

        # ==========================================================
        # REMOVE FROM DISTANCE MATRIX
        # ==========================================================

        # Convert all column names to string
        DistanceBing.columns = DistanceBing.columns.astype(str)

        # If first column contains row IDs, convert to string
        DistanceBing.iloc[:, 0] = DistanceBing.iloc[:, 0].astype(str)

        # Remove warehouse columns
        Distance1 = DistanceBing.drop(
            columns=[col for col in DistanceBing.columns if col in Warehouse_ID],
            errors='ignore'
        )

        # Remove FCI rows
        Distance2 = Distance1[
            ~Distance1.iloc[:, 0].isin(FPS_ID)
        ]

        # ==========================================================
        # SAVE OUTPUT
        # ==========================================================
        with pd.ExcelWriter(
            'Backend//Bihar_Distance_L1.xlsx',
            engine='openpyxl'
        ) as writer:

            Distance2.to_excel(
                writer,
                sheet_name='BG_BG',
                index=False
            )

        print("Distance matrix updated successfully.")
        print("Final Shape:", Distance2.shape)
        
        Cost=pd.ExcelFile('Backend//Bihar_Distance_L1.xlsx')
        BG_BG = pd.read_excel(Cost,sheet_name="BG_BG")
        Cost.close()
        
        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)        

        Distance_BG_BG = {}
        column_list_BG_BG = list(BG_BG.columns)
        #print(column_list_BG_BG)
        row_list_BG_BG = list(BG_BG.iloc[:, 0])
        #print(row_list_BG_BG )  
        for ind in df5.index:
            from_code= df5['From_ID'][ind] 
            to_code = df5['To_ID'][ind]
            if to_code in row_list_BG_BG and from_code in column_list_BG_BG:
                index_i = row_list_BG_BG.index(to_code)
                index_j = column_list_BG_BG.index(from_code)
                key = str(to_code) + "_" + str(from_code)
                Distance_BG_BG[key]= BG_BG.iloc[index_i , index_j]
                #print(Distance_BG_BG[key])
            
        #df5["Tagging"]=df5['To_ID']+ '_' + df5['From_ID']
        df5["Tagging"] = df5['To_ID'].astype(str) + '_' + df5['From_ID'].astype(str)
        df5['Distance'] = df5['Tagging'].map(Distance_BG_BG)
        df5 = df5.replace('',pd.NaT).fillna('shallu')
        d5=df5.loc[df5['Distance'] == "shallu"]
        df5.to_excel('Backend//Result_Sheet12.xlsx',
                         sheet_name='Warehouse_FPS')

        
# ----------------------------------------------------------------------------------------------------------------------------------------------
# -------------------- READ INPUT --------------------
        Result_Sheet1 = pd.ExcelFile("Backend//Result_Sheet12.xlsx")
        df6 = pd.read_excel(Result_Sheet1, sheet_name="Warehouse_FPS")
        Result_Sheet1.close()

        df7 = df6.loc[df6['Distance'] == "shallu"].reset_index(drop=True)

        # -------------------- API Details --------------------
        auth_url = 'https://kerala.pmgatishakti.gov.in/DFPD/authenticate'
        distance_url = 'https://kerala.pmgatishakti.gov.in/PMGatishaktiApiService/dfpdapi/roaddistance'

        auth_payload = {
            "username": "DFPD_C",
            "password": "W9Vtb8WKkt3"
        }

        FILE_PATH = 'distanceIndent.json'

        # -------------------- Get Token --------------------
        def get_token():
            try:
                response = requests.post(auth_url, json=auth_payload, timeout=240)
                if response.status_code == 200:
                    return response.json().get('token')
                return None
            except requests.exceptions.RequestException as e:
                print("Auth API Error:", e)
                raise Exception("PMGatiShakti Authentication Service is currently unavailable. Please check your internet connection or try again later.")

        # -------------------- Batch API --------------------
        def process_batch(df_batch, token):
            headers = {'Authorization': f'Bearer {token}'}

            data = {
                "parameter": [{
                    "src_lng": row["From_Long"],
                    "src_lat": row["From_Lat"],
                    "dest_lng": row["To_Long"],
                    "dest_lat": row["To_Lat"]
                } for _, row in df_batch.iterrows()]
            }

            try:
                with open(FILE_PATH, 'w') as f:
                    json.dump(data, f, indent=4)

                with open(FILE_PATH, 'rb') as f:
                    files = {'LatsLongsFile': f}
                    response = requests.post(distance_url, headers=headers, files=files, timeout=240)

                return response
            except requests.exceptions.RequestException as e:
                print("Batch API Error:", e)
                raise Exception("PMGatiShakti Distance Service is currently unavailable. Please check your internet connection or try again later.")
            except Exception as e:
                print("Batch API Error:", e)
                return None

        # -------------------- Single Row API --------------------
        def process_single_row(row, token):
            headers = {'Authorization': f'Bearer {token}'}

            data = {
                "parameter": [{
                    "src_lng": row["From_Long"],
                    "src_lat": row["From_Lat"],
                    "dest_lng": row["To_Long"],
                    "dest_lat": row["To_Lat"]
                }]
            }

            try:
                with open(FILE_PATH, 'w') as f:
                    json.dump(data, f, indent=4)

                with open(FILE_PATH, 'rb') as f:
                    files = {'LatsLongsFile': f}
                    response = requests.post(distance_url, headers=headers, files=files, timeout=240)

                if response.status_code != 200:
                    return 0

                res_json = response.json()
                api_data = res_json.get("data", [])

                if len(api_data) == 0:
                    return 0

                distance = api_data[0].get("distance")

                if isinstance(distance, (int, float)):
                    return distance

                return 0
            except requests.exceptions.RequestException as e:
                print("Row API Error (Connection):", e)
                return "CONNECTION_ERROR"
            except Exception as e:
                print("Row API Error:", e)
                return 0

        # ----------------------------------------------------------------------------------------------------------------------------------------------
        # -------------------- MAIN PROCESS --------------------

        batch_size = 1000
        total_rows = len(df7)
        num_batches = (total_rows + batch_size - 1) // batch_size

        dist3 = []

        for batch_num in range(num_batches):
            print(f"Processing batch {batch_num+1}/{num_batches}")

            start_idx = batch_num * batch_size
            end_idx = min((batch_num + 1) * batch_size, total_rows)
            df_batch = df7.iloc[start_idx:end_idx]

            token = get_token()
            if not token:
                data_err = {"status": 0, "message": "Failed to retrieve PMGatiShakti token."}
                return json.dumps(data_err, indent=1)

            response = process_batch(df_batch, token)

            fallback_required = False

            if not response or response.status_code != 200:
                fallback_required = True
            else:
                try:
                    response_json = response.json()
                    api_data = response_json.get("data", [])

                    if len(api_data) != len(df_batch):
                        fallback_required = True
                    else:
                        for row_data in api_data:
                            distance = row_data.get("distance")
                            if not isinstance(distance, (int, float)):
                                fallback_required = True
                                break

                except Exception:
                    fallback_required = True

            # ---------------- FALLBACK ----------------
            if fallback_required:
                print(f"Batch {batch_num+1} failed -> switching to row-wise")

                for _, row in df_batch.iterrows():
                    distance = process_single_row(row, token)
                    if distance == "CONNECTION_ERROR":
                        data_err = {"status": 0, "message": "PMGatiShakti API is currently unavailable or there is an internet connection issue. Please check your connection and try again."}
                        return json.dumps(data_err, indent=1)

                    if distance == 0:
                        print(f"Distance set to 0 for From {row['From_ID']} -> To {row['To_ID']}")

                    dist3.append(distance)

            # ---------------- NORMAL ----------------
            else:
                for row_data in api_data:
                    dist3.append(row_data.get("distance"))

        

        # -------------------- UPDATE DATA --------------------
        df7["Distance"] = dist3

        df9 = df6.loc[df6['Distance'] != "shallu"]

        columns = [
            'Scenario','From','From_State','From_District','From_ID','From_Name',
            'From_Lat','From_Long','To','To_ID','To_Name','To_State','To_District',
            'To_Lat','To_Long','commodity','quantity','Distance'
        ]

        df9 = df9[columns]
        df7 = df7[columns]

        df10 = pd.concat([df9, df7], ignore_index=True)

        # -------------------- FINAL RESULT --------------------
        result = (df10['quantity'] * df10['Distance']).sum()

        print("Total Result:", result)

        # -------------------- SAVE OUTPUT --------------------
        df10.to_excel('Backend//Result_Sheet_leg1.xlsx', sheet_name='Warehouse_FPS', index=False)

        print("Process Completed Successfully")
# ----------------------------------------------------------------------------------------------------------------------------------------------        
                     
        Total_Demand=  float(WH['Allocation_Wheat'].sum()) + float(WH['Allocation_Rice'].sum())     


        data ={}        
        
        data["Scenario"]="Inter"
        data["Scenario_Baseline"] = "Baseline"
        
        data["WH_Used"] = df5['From_ID'].nunique()
        data["WH_Used_Baseline"] = "465"
        
        data["FPS_Used"] = df5['To_ID'].nunique()
        data["FPS_Used_Baseline"] = "459"
        
        data['Demand'] = Total_Demand
        data['Demand_Baseline'] = "46,05,917.05"
        
        
        
        data['Total_QKM'] = float(result)
        data['Total_QKM_Baseline'] = "15,71,38,370.9"
        
        data['Average_Distance'] = float(round(result, 2)) / Total_Demand
        data['Average_Distance_Baseline'] = "34.11"

        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)                     
        
        save_to_database_leg1(month, year, applicable, scenario_type)
        save_monthly_data_leg1(month, year, float(result))
        
        
        def delete_files(file_paths):
            for file_path in file_paths:
                try:
                    if os.path.exists(file_path):  # Check if the file exists
                        os.remove(file_path)  # Delete the file
                        #print(f"{file_path} has been deleted.")
                    else:
                        print(f"{file_path} does not exist.")
                except Exception as e:
                    print(f"Error deleting {file_path}: {e}")

        # List of files to delete
        files_to_delete = [
            'Backend/Bihar_Distance_L1.xlsx',
            'Backend/Result_Sheet12.xlsx',
            'Backend//Tagging_Sheet_Pre11_leg1.xlsx',
            
        ]

        # Call the function to delete the files
        delete_files(files_to_delete)
		
        
        json_data = json.dumps(data)
        json_object = json.loads(json_data)

        if os.path.exists('ouputPickle.pkl'):
            os.remove('ouputPickle.pkl')

        # open pickle file
        dbfile1 = open('ouputPickle.pkl', 'ab')
    
    
    
    
    elif scenario_type == "rice_dcp_wheat_fci_dcp":
        message = 'DataFile file is incorrect'
        try:
            USN = pd.ExcelFile('Backend//Data_2.xlsx')
            month = request.form.get('month')        
            year = request.form.get('year')
            scenario_type = request.form.get('type')
            applicable = request.form.get('applicable')
        except Exception as e:
            data = {}
            data['status'] = 0
            data['message'] = message
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)
        input = pd.ExcelFile('Backend//Data_2.xlsx')
        node1 = pd.read_excel(input,sheet_name="A.2 FCI")
        node2 = pd.read_excel(input,sheet_name="A.1 Warehouse")
        node3 = pd.read_excel(input,sheet_name="A.2 DCP")

        dist = [[0 for a in range(len(node2["SW_ID"]))] for b in range(len(node1["WH_ID"]))]
        phi_1 = []
        phi_2 = []
        delta_phi = []
        delta_lambda = []
        R = 6371 

        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)

        for i in node1.index:
            for j in node2.index:
                phi_1=math.radians(node1["WH_Lat"][i])
                phi_2=math.radians(node2["SW_lat"][j])
                delta_phi=math.radians(node2["SW_lat"][j]-node1["WH_Lat"][i])
                delta_lambda=math.radians(node2["SW_Long"][j]-node1["WH_Long"][i])
                x=math.sin(delta_phi / 2.0) ** 2 + math.cos(phi_1) * math.cos(phi_2) * math.sin(delta_lambda / 2.0) ** 2
                y=2 * math.atan2(math.sqrt(x), math.sqrt(1 - x))
                dist[i][j]=R*y
                
       


        dist1 = [[0 for a in range(len(node2["SW_ID"]))] for b in range(len(node3["WH_ID"]))]
        phi_11 = []
        phi_21 = []
        delta_phi1 = []
        delta_lambda1 = []
        R = 6371 

        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)

        for i in node3.index:
            for j in node2.index:
                phi_11=math.radians(node3["WH_Lat"][i])
                phi_21=math.radians(node2["SW_lat"][j])
                delta_phi1=math.radians(node2["SW_lat"][j]-node3["WH_Lat"][i])
                delta_lambda1=math.radians(node2["SW_Long"][j]-node3["WH_Long"][i])
                x=math.sin(delta_phi1 / 2.0) ** 2 + math.cos(phi_11) * math.cos(phi_21) * math.sin(delta_lambda1 / 2.0) ** 2
                y=2 * math.atan2(math.sqrt(x), math.sqrt(1 - x))
                dist1[i][j]=R*y
                
        

        
        FCI = pd.read_excel(USN, sheet_name='A.2 FCI', index_col=None)
        WH = pd.read_excel(USN, sheet_name='A.1 Warehouse', index_col=None)
        DCP = pd.read_excel(USN, sheet_name='A.2 DCP', index_col=None)

        if 'Rice' in WH.columns:
            WH['Demand_Rice'] = WH['Rice']
            WH['Allocation_Rice'] = WH['Rice']
        if 'Wheat' in WH.columns:
            WH['Demand_Wheat'] = WH['Wheat']
            WH['Allocation_Wheat'] = WH['Wheat']

        FCI['WH_District'] = FCI['WH_District'].apply(lambda x: x.replace(' ', ''))
        WH['SW_District'] = WH['SW_District'].apply(lambda x: x.replace(' ', ''))
        DCP['WH_District'] = DCP['WH_District'].apply(lambda x: x.replace(' ', ''))
        
        excel_path = "Backend//Distance_Intial_L1.xlsx"
        output_path = "Backend//Distance_Initial_L1_updated.xlsx"
        sheet_name = "BG_BG"
        excel_password = "distf"

        # ---------- Step 1: Get latest optimisation table ---------- #
        conn = connect_to_database()
        cursor = conn.cursor(dictionary=True)

        cursor.execute("""
            SELECT id
            FROM optimised_table_leg1
            WHERE month = %s and year = %s
            ORDER BY last_updated DESC
            LIMIT 1
        """, (month, year))
        opt = cursor.fetchone()

        updates = []
        if opt:
            table_name = f"optimiseddata_leg1_{opt['id']}"
            cursor.execute("SHOW TABLES LIKE %s", (table_name,))
            table_exists = cursor.fetchone()
            if table_exists:
                cursor.execute(f"""
                    SELECT from_id, to_id, new_distance_district, approve_district
                    FROM `{table_name}`
                    WHERE LOWER(approve_district) = 'no'
                """)
                updates = cursor.fetchall()

        cursor.close()
        conn.close()

        if updates:
            # ---------- Step 2: Decrypt Excel ---------- #
            decrypted = io.BytesIO()
            with open(excel_path, "rb") as f:
                office = msoffcrypto.OfficeFile(f)
                office.load_key(password=excel_password)
                office.decrypt(decrypted)

            decrypted.seek(0)

            # ---------- Step 3: Read Excel and Parse All Sheets ---------- #
            xl = pd.ExcelFile(decrypted, engine="openpyxl")
            sheets = {name: xl.parse(name) for name in xl.sheet_names}
            df = sheets[sheet_name]

            df.rename(columns={df.columns[0]: "to_id"}, inplace=True)
            df["to_id"] = df["to_id"].astype(str)
            df.set_index("to_id", inplace=True)

            df.columns = df.columns.astype(str)

            # ---------- Step 4: Intelligent Update + Minimal Append ---------- #
            updated_cells = 0
            appended_routes = 0

            for row in updates:
                from_id = str(row["from_id"])
                to_id = str(row["to_id"])
                new_dist = row.get("new_distance_district")
                if new_dist is not None:
                    try:
                        distance = float(new_dist)
                        if distance > 0:
                            # ---- Ensure ROW exists ---- #
                            if to_id not in df.index:
                                df.loc[to_id] = 0
                                appended_routes += 1

                            # ---- Ensure COLUMN exists ---- #
                            if from_id not in df.columns:
                                df[from_id] = 0
                                appended_routes += 1

                            # ---- Update the specific cell ---- #
                            if df.at[to_id, from_id] != distance:
                                df.at[to_id, from_id] = distance
                                updated_cells += 1
                    except (ValueError, TypeError):
                        pass

            # ---------- Step 5: Save Excel with All Sheets and Encrypt ---------- #
            output_path = "Backend//Distance_Initial_L1_updated.xlsx"
            sheets[sheet_name] = df.reset_index()

            plain_buf = io.BytesIO()
            with pd.ExcelWriter(plain_buf, engine="xlsxwriter") as writer:
                for name, sheet_df in sheets.items():
                    sheet_df.to_excel(writer, sheet_name=name, index=False)
            plain_buf.seek(0)

            file = msoffcrypto.format.ooxml.OOXMLFile(plain_buf)
            with open(output_path, "wb") as f_out:
                file.encrypt(excel_password, f_out)
        else:
            import shutil
            shutil.copy(excel_path, output_path)

        
        model = LpProblem('Supply-Demand-Problem', LpMinimize)

        Variable3 = []
        
        for i in range(len(DCP['WH_ID'])):
            for j in range(len(WH['SW_ID'])):
                Variable3.append(str(DCP['WH_ID'][i]) + '_'
                                 + str(DCP['WH_District'][i]) + '_'
                                 + str(WH['SW_ID'][j]) + '_'
                                 + str(WH['SW_District'][j]) + f'_FRice_{i}_{j}')
                                 
        Variable4 = []
        for i in range(len(FCI['WH_ID'])):
            for j in range(len(WH['SW_ID'])):
                Variable4.append(str(FCI['WH_ID'][i]) + '_'
                                 + str(FCI['WH_District'][i]) + '_'
                                 + str(WH['SW_ID'][j]) + '_'
                                 + str(WH['SW_District'][j]) + f'_Wheat_{i}_{j}')
                                 
                                 
        Variable5 = []
        
        for i in range(len(DCP['WH_ID'])):
            for j in range(len(WH['SW_ID'])):
                Variable5.append(str(DCP['WH_ID'][i]) + '_'
                                 + str(DCP['WH_District'][i]) + '_'
                                 + str(WH['SW_ID'][j]) + '_'
                                 + str(WH['SW_District'][j]) + f'_Wheat_{i}_{j}')

        # Variables for Wheat from lEVEL2 TO FPS

        DV_Variables3 = LpVariable.matrix('X', Variable3, cat='float',
                lowBound=0)
        Allocation3 = np.array(DV_Variables3).reshape(len(DCP['WH_ID']),
                len(WH['SW_ID']))
                
        
                
        DV_Variables4 = LpVariable.matrix('Y', Variable4, cat='float',
                lowBound=0)
        Allocation4 = np.array(DV_Variables4).reshape(len(FCI['WH_ID']),
                len(WH['SW_ID']))

        DV_Variables5 = LpVariable.matrix('Y', Variable5, cat='float',
                lowBound=0)
        Allocation5 = np.array(DV_Variables5).reshape(len(DCP['WH_ID']),
                len(WH['SW_ID']))
        
        State_Riceprocurement = {}
        for i in range(len(DCP["WH_District"])):
            District_Name = DCP["WH_District"][i]
            if District_Name not in State_Riceprocurement:
                State_Riceprocurement[District_Name] = float(DCP["Procurement Rice"][i])
            else:
                State_Riceprocurement[District_Name] += float(DCP["Procurement Rice"][i])
           

        District_DemandRice = {}
        for i in range(len(WH["SW_District"])):
            District_Name_FPS = WH["SW_District"][i]
            if District_Name_FPS not in District_DemandRice:
                District_DemandRice[District_Name_FPS] = float(WH["Demand_Rice"][i])
            else:
                District_DemandRice[District_Name_FPS] += float(WH["Demand_Rice"][i]) 
        
        District_Name = []
        District_Name2=[]
        District_Name = [i for i in District_DemandRice if i not in State_Riceprocurement]
        District_Name4 = [i for i in State_Riceprocurement if i not in District_DemandRice]
        District_Name2 = [i for i in District_DemandRice if i in State_Riceprocurement and District_DemandRice[i] >= State_Riceprocurement[i]]
        District_Name_1 = {}
        District_Name_1['District_Name_All'] = District_Name + District_Name2
        District_Name3 = [i for i in District_DemandRice if i in State_Riceprocurement and District_DemandRice[i] <= State_Riceprocurement[i]]
        
        
        name1 = []
        lst1 = []
        for j in range(len(DV_Variables3)):
            name1 = str(DV_Variables3[j])
            lst1 = name1.split("_")
            if lst1[2] in District_Name3 and lst1[4] in District_Name3 and lst1[2]!=lst1[4]:
                model+=DV_Variables3[j]==0
                #print(DV_Variables1[j]==0)
                
        name2 = []
        lst2 = []
        for j in range(len(DV_Variables3)):
            name2 = str(DV_Variables3[j])
            lst2 = name2.split("_")
            if lst2[2] in District_Name2 and lst2[4] in District_Name3:
                model+=DV_Variables3[j]==0
                #print(DV_Variables3[j]==0)
                
        name3 = []
        lst3 = []
        for j in range(len(DV_Variables3)):
            name3 = str(DV_Variables3[j])
            lst3 = name3.split("_")
            if lst3[2] in District_Name2 and lst3[4] in District_Name2 and lst3[2]!=lst3[4]:
                model+=DV_Variables3[j]==0
                #print(DV_Variables1[j]==0)

        name4 = []
        lst4 = []
        for j in range(len(DV_Variables3)):
            name4 = str(DV_Variables3[j])
            lst4 = name4.split("_")
            if lst4[2] in District_Name4 and lst4[4] in District_Name3:
                model+=DV_Variables3[j]==0
                #print(DV_Variables1[j]==0)

        District_Capacity = {}
        for i in range(len(FCI["WH_District"])):
            District_Name = FCI["WH_District"][i]
            if District_Name not in District_Capacity:
                District_Capacity[District_Name] = float(FCI["Allotment_Wheat"][i])
            else:
                District_Capacity[District_Name] += float(FCI["Allotment_Wheat"][i])
                
        District_DemandWheat = {}
        for i in range(len(WH["SW_District"])):
            District_Name_FPS = WH["SW_District"][i]
            if District_Name_FPS not in District_DemandWheat :
                District_DemandWheat [District_Name_FPS] = float(WH["Demand_Wheat"][i])
            else:
                District_DemandWheat [District_Name_FPS] += float(WH["Demand_Wheat"][i])
                
                
        District_Name_wheat = []
        District_Name2_wheat=[]
        District_Name_wheat= [i for i in District_DemandWheat if i not in District_Capacity]
        District_Name4_wheat = [i for i in District_Capacity if i not in District_DemandWheat]
        District_Name2_wheat = [i for i in District_DemandWheat if i in District_Capacity and District_DemandWheat[i] >= District_Capacity[i]]
        District_Name_1_wheat = {}
        District_Name_1_wheat['District_Name_All'] = District_Name_wheat + District_Name2_wheat
        District_Name3_wheat = [i for i in District_DemandWheat if i in District_Capacity and District_DemandWheat[i] <= District_Capacity[i]]
        
        
        name5 = []
        lst5 = []
        for j in range(len(DV_Variables4)):
            name5 = str(DV_Variables4[j])
            lst5 = name5.split("_")
            if lst5[2] in District_Name3_wheat and lst5[4] in District_Name3_wheat and lst5[2]!=lst5[4]:
                model+=DV_Variables4[j]==0
                #print(DV_Variables1[j]==0)
                
        name6 = []
        lst6 = []
        for j in range(len(DV_Variables4)):
            name6 = str(DV_Variables4[j])
            lst6 = name6.split("_")
            if lst6[2] in District_Name2_wheat and lst6[4] in District_Name3_wheat:
                model+=DV_Variables4[j]==0
                #print(DV_Variables1[j]==0)
                
        name7 = []
        lst7 = []
        for j in range(len(DV_Variables4)):
            name7 = str(DV_Variables4[j])
            lst7 = name7.split("_")
            if lst7[2] in District_Name2_wheat and lst7[4] in District_Name2_wheat and lst7[2]!=lst7[4]:
                model+=DV_Variables4[j]==0
                #print(DV_Variables1[j]==0)

        name8 = []
        lst8 = []
        for j in range(len(DV_Variables4)):
            name8 = str(DV_Variables4[j])
            lst8 = name8.split("_")
            if lst8[2] in District_Name4_wheat and lst8[4] in District_Name3_wheat:
                model+=DV_Variables4[j]==0
                #print(DV_Variables1[j]==0)
                
        State_Wheatprocurement = {}
        for i in range(len(DCP["WH_District"])):
            District_Name = DCP["WH_District"][i]
            if District_Name not in State_Wheatprocurement:
                State_Wheatprocurement[District_Name] = float(DCP["Procurement Wheat"][i])
            else:
                State_Wheatprocurement[District_Name] += float(DCP["Procurement Wheat"][i])
                
                
                
        District_DemandWheat1 = {}
        for i in range(len(WH["SW_District"])):
            District_Name_FPS = WH["SW_District"][i]
            if District_Name_FPS not in District_DemandWheat1 :
                District_DemandWheat1 [District_Name_FPS] = float(WH["Demand_Wheat"][i])
            else:
                District_DemandWheat1 [District_Name_FPS] += float(WH["Demand_Wheat"][i])

        
        District_Name_wheat1 = []
        District_Name2_wheat1=[]
        District_Name_wheat1= [i for i in District_DemandWheat1 if i not in State_Wheatprocurement]
        District_Name4_wheat1 = [i for i in State_Wheatprocurement if i not in District_DemandWheat1]
        District_Name2_wheat1 = [i for i in District_DemandWheat1 if i in State_Wheatprocurement and District_DemandWheat1[i] >= State_Wheatprocurement[i]]
        District_Name_1_wheat1 = {}
        District_Name_1_wheat1['District_Name_All'] = District_Name_wheat + District_Name2_wheat
        District_Name3_wheat1 = [i for i in District_DemandWheat1 if i in State_Wheatprocurement and District_DemandWheat1[i] <= State_Wheatprocurement[i]]
        
        
        name9 = []
        lst9 = []
        for j in range(len(DV_Variables5)):
            name9 = str(DV_Variables5[j])
            lst9 = name9.split("_")
            if lst9[2] in District_Name3_wheat1 and lst9[4] in District_Name3_wheat1 and lst9[2]!=lst9[4]:
                model+=DV_Variables5[j]==0
                #print(DV_Variables1[j]==0)
                
        name10 = []
        lst10 = []
        for j in range(len(DV_Variables5)):
            name10 = str(DV_Variables5[j])
            lst10 = name10.split("_")
            if lst10[2] in District_Name2_wheat1 and lst10[4] in District_Name3_wheat1:
                model+=DV_Variables5[j]==0
                #print(DV_Variables1[j]==0)
                
        name11 = []
        lst11 = []
        for j in range(len(DV_Variables5)):
            name11 = str(DV_Variables5[j])
            lst11 = name11.split("_")
            if lst11[2] in District_Name2_wheat1 and lst11[4] in District_Name2_wheat1 and lst11[2]!=lst11[4]:
                model+=DV_Variables5[j]==0
                #print(DV_Variables1[j]==0)

        name12 = []
        lst12 = []
        for j in range(len(DV_Variables5)):
            name12 = str(DV_Variables5[j])
            lst12 = name12.split("_")
            if lst12[2] in District_Name4_wheat1 and lst12[4] in District_Name3_wheat1:
                model+=DV_Variables5[j]==0
                #print(DV_Variables1[j]==0)
        
        
        
        
        

        
        allCombination3 = []
        allCombination4 = []
        allCombination5 = []

        for i in range(len(dist1)):
            for j in range(len(WH['SW_ID'])):
                allCombination3.append(Allocation3[i][j] * dist1[i][j])
        
        for i in range(len(dist1)):
            for j in range(len(WH['SW_ID'])):
                allCombination5.append(Allocation5[i][j] * dist1[i][j])
                
        for i in range(len(dist)):
            for j in range(len(WH['SW_ID'])):
                allCombination4.append(Allocation4[i][j] * dist[i][j])
                
                

        model += lpSum(allCombination3 + allCombination4 + allCombination5)
        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)

        # Demand Constraints for Wheat

        for i in range(len(WH['SW_ID'])):
            model += ((lpSum(Allocation4[j][i] for j in range(len(FCI['WH_ID'
                           ])))) + (lpSum(Allocation5[j][i] for j in range(len(DCP['WH_ID'
                           ])))) >= WH['Demand_Wheat'][i])
                           
        for i in range(len(WH['SW_ID'])):
            model += (lpSum(Allocation3[j][i] for j in range(len(DCP['WH_ID'
                           ]))) >= WH['Demand_Rice'][i])

        # Supply Constraints for Warehouses

        for i in range(len(DCP['WH_ID'])):
            model += ((lpSum(Allocation3[i][j] for j in range(len(WH['SW_ID'
                           ]))))  <= DCP['Procurement Rice'][i])
         
        for i in range(len(DCP['WH_ID'])):
            model += ((lpSum(Allocation3[i][j] for j in range(len(WH['SW_ID'
                           ]))))  >= DCP['Procurement Rice'][i])
                           
        for i in range(len(FCI['WH_ID'])):
            model += ((lpSum(Allocation4[i][j] for j in range(len(WH['SW_ID'
                           ]))))  <= FCI['Allotment_Wheat'][i])
                           
        
                           
        for i in range(len(DCP['WH_ID'])):
            model += ((lpSum(Allocation5[i][j] for j in range(len(WH['SW_ID'
                           ]))))  >= DCP['Procurement Wheat'][i])
                           
                           
        for i in range(len(DCP['WH_ID'])):
            model += ((lpSum(Allocation5[i][j] for j in range(len(WH['SW_ID'
                           ]))))  <= DCP['Procurement Wheat'][i])
                           
        model+=(lpSum(Allocation4[i][j] for i in range(len(FCI["WH_ID"])) for j in range(len(WH["SW_ID"])))<=lpSum(WH["Demand_Wheat"][i] for i in range(len(WH["SW_ID"])))-lpSum(DCP["Procurement Wheat"][j] for j in range(len(DCP["WH_ID"]))))
        
        model+=(lpSum(Allocation4[i][j] for i in range(len(FCI["WH_ID"])) for j in range(len(WH["SW_ID"])))>=lpSum(WH["Demand_Wheat"][i] for i in range(len(WH["SW_ID"])))-lpSum(DCP["Procurement Wheat"][j] for j in range(len(DCP["WH_ID"]))))

        
        


       
        model.solve(COIN_CMD(path=get_cbc_path(),msg=0,gapRel=0.03,timeLimit=600))

        
        status = LpStatus[model.status]

        if status != "Optimal":
            print("Optimization failed:", status)

            data = {
                "status": 0,
                "message": "Infeasible or Unbounded Solution"
            }

            return json.dumps(data, indent=1)

        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)

        
        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)        
        
       

        Original_Cost = 100000000
        total = Original_Cost

        data = {}
        

        
        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)

        Output_File = open('Backend//Inter_District1_leg1.csv', 'w')
        for v in model.variables():
            if v.value() > 0:
                Output_File.write(v.name + '\t' + str(v.value()) + '\n')

        Output_File = open('Backend//Inter_District1_leg1.csv', 'w')
        for v in model.variables():
            if v.value() > 0:
                Output_File.write(v.name + '\t' + str(v.value()) + '\n')

        df9 = pd.read_csv('Backend//Inter_District1_leg1.csv',header=None)

        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)        

        df9.columns = ['Tagging']
        df9[[
            'Var',
            'WH_ID',
            'W_D',
            'SW_ID',
            'SW_D',
            'commodity_Value',
            ]] = df9[df9.columns[0]].str.split('_', n=5, expand=True)
        del df9[df9.columns[0]]
        df9[['commodity', 'Values']] = df9['commodity_Value'].str.split('\\t', n=1, expand=True)
        del df9['commodity_Value']
        df9['commodity'] = df9['commodity'].str.split('_').str[0]
        
        df9 = df9.drop(np.where(df9['commodity'] == 'Wheat1')[0])
        def convert_to_numeric(value):
            try:
                return pd.to_numeric(value)
            except ValueError:
                return value
        
        
        df9['WH_ID'] = df9['WH_ID'].apply(convert_to_numeric)
        df9['SW_ID'] = df9['SW_ID'].apply(convert_to_numeric)
        
        df9.to_excel('Backend//Tagging_Sheet_Pre_leg1.xlsx', sheet_name='BG_FPS')
        df31 = pd.read_excel('Backend//Tagging_Sheet_Pre_leg1.xlsx')
        
        USN = pd.ExcelFile('Backend//Data_2.xlsx')
        WH = pd.read_excel(USN, sheet_name='A.1 Warehouse', index_col=None)
        FCI = pd.read_excel(USN, sheet_name='A.2 FCI', index_col=None)        # Convert to object type, adjust as needed
        DCP = pd.read_excel(USN, sheet_name='A.2 DCP', index_col=None)  
        
        columns_to_include = ["WH_District","WH_Name","WH_ID",	"Type of WH",	"WH_Lat",	"WH_Long"]
        df1_selected = FCI[columns_to_include]
        df2_selected = DCP[columns_to_include]
        
        FCI = pd.concat([df1_selected, df2_selected], ignore_index=True)

        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)
        


        df4 = pd.merge(df31, FCI, on='WH_ID', how='inner')
        #df4 = pd.merge(df31, FCI, on='WH_ID', how='inner')
        df4 = df4[[
            'WH_ID',
            'WH_Name',
            'WH_District',
            'WH_Lat',
            'WH_Long',
            'Type of WH',
            'SW_ID',
            'commodity',
            'Values',
            ]]
        df4 = pd.merge(df4, WH, on='SW_ID', how='inner')
        df51 = df4[[
            'WH_ID',
            'WH_Name',
            'WH_District',
            'WH_Lat',
            'WH_Long',
            'Type of WH',
            'SW_ID',
            'SW_Name',
            'SW_District',
            'SW_lat',
            'SW_Long',
            'commodity',
            'Values',
            ]]
        df51.insert(0, 'Scenario', 'Optimized')
        df51.insert(2, 'From_State', 'Bihar')
        df51.insert(7, 'To', 'TPDS')
        df51.insert(8, 'To_State', 'Bihar')
        
        df51.rename(columns={
            'WH_ID': 'From_ID',
            'WH_Name': 'From_Name',
            'WH_Lat': 'From_Lat',
            'Type of WH': 'From',
            'WH_Long': 'From_Long',
            }, inplace=True)
        df51.rename(columns={
            'SW_ID': 'To_ID',
            'SW_Name': 'To_Name',
            'SW_lat': 'To_Lat',
            'SW_Long': 'To_Long',
            'Values':'quantity',
            }, inplace=True)
        df51.rename(columns={'WH_District': 'From_District',
                   'SW_District': 'To_District'}, inplace=True)
        df51 = df51.loc[:, [
            'Scenario',
            'From',
            'From_State',
            'From_District',
            'From_ID',
            'From_Name',
            'From_Lat',
            'From_Long',
            'To',
            'To_ID',
            'To_Name',
            'To_State',
            'To_District',
            'To_Lat',
            'To_Long',
            'commodity',
            'quantity',
            ]]
            
        def convert_to_numeric(value):
            try:
                return pd.to_numeric(value)
            except ValueError:
                return value
                
        
        df51['From_ID'] = df51['From_ID'].apply(convert_to_numeric)
        df51['To_ID'] = df51['To_ID'].apply(convert_to_numeric)   
        
        df51.to_excel('Backend//Tagging_Sheet_Pre11_leg1.xlsx', sheet_name='BG_FPS1')
        data1 = pd.ExcelFile("Backend//Tagging_Sheet_Pre11_Leg1.xlsx")
        df5 = pd.read_excel(data1,sheet_name="BG_FPS1")
        data1.close()
        
       
        
        # ==========================================================
        # READ MASTER DATA
        # ==========================================================
        input_file = pd.ExcelFile('Backend//Data_2.xlsx')

        # Warehouse Sheet
        node1 = pd.read_excel(input_file, sheet_name="A.1 Warehouse")

        node1['SW_ID'] = node1['SW_ID'].astype(str).str.strip()

        node1['Lat_Long_r'] = (
            node1[['SW_lat', 'SW_Long']]
            .astype(float)
            .round(3)
            .astype(str)
            .agg(','.join, axis=1)
        )

        # FCI Sheet
        node2 = pd.read_excel(input,sheet_name="A.2 FCI")
        node3 = pd.read_excel(input,sheet_name="A.2 DCP")
        columns_to_include = ["WH_District","WH_Name","WH_ID",	"Type of WH",	"WH_Lat",	"WH_Long"]
        df1_selected = node2[columns_to_include]
        df2_selected = node3[columns_to_include]
        
        node2 = pd.concat([df1_selected, df2_selected], ignore_index=True)

        node2['WH_ID'] = node2['WH_ID'].astype(str).str.strip()

        node2['Lat_Long_r'] = (
            node2[['WH_Lat', 'WH_Long']]
            .astype(float)
            .round(3)
            .astype(str)
            .agg(','.join, axis=1)
        )

        
        updated_excel_path = 'Backend//Distance_Initial_L1_updated.xlsx'
        ref_excel_path = updated_excel_path if os.path.exists(updated_excel_path) else 'Backend//Distance_Intial_L1.xlsx'
        DistanceBing = read_protected_excel(ref_excel_path, 'distf', sheet_name='BG_BG')
        Warehouse = read_protected_excel(ref_excel_path, 'distf', sheet_name='Warehouse')
        FCI = read_protected_excel(ref_excel_path, 'distf', sheet_name='FCI')
        
                # ==========================================================
        # STANDARDIZE IDS
        # ==========================================================
        Warehouse['SW_ID'] = Warehouse['SW_ID'].astype(str).str.strip()
        FCI['WH_ID'] = FCI['WH_ID'].astype(str).str.strip()

        # ==========================================================
        # ROUND LAT LONG IN DISTANCE FILE
        # ==========================================================
        Warehouse['Lat_Long_r'] = (
            Warehouse['Lat_Long']
            .astype(str)
            .str.split(',', expand=True)
            .astype(float)
            .round(3)
            .astype(str)
            .agg(','.join, axis=1)
        )

        FCI['Lat_Long_r'] = (
            FCI['Lat_Long']
            .astype(str)
            .str.split(',', expand=True)
            .astype(float)
            .round(3)
            .astype(str)
            .agg(','.join, axis=1)
        )

        # ==========================================================
        # FIND WAREHOUSES WITH CHANGED LAT LONG
        # ==========================================================
        War = pd.merge(
            node1[['SW_ID', 'Lat_Long_r']],
            Warehouse[['SW_ID', 'Lat_Long_r']],
            on='SW_ID',
            how='inner',
            suffixes=('_master', '_distance')
        )

        Warehouse_ID = War.loc[
            War['Lat_Long_r_master'] != War['Lat_Long_r_distance'],
            'SW_ID'
        ].astype(str).unique()

        print("Warehouse IDs to remove:", len(Warehouse_ID))

        # ==========================================================
        # FIND FCI WITH CHANGED LAT LONG
        # ==========================================================
        FPS1 = pd.merge(
            node2[['WH_ID', 'Lat_Long_r']],
            FCI[['WH_ID', 'Lat_Long_r']],
            on='WH_ID',
            how='inner',
            suffixes=('_master', '_distance')
        )

        FPS_ID = FPS1.loc[
            FPS1['Lat_Long_r_master'] != FPS1['Lat_Long_r_distance'],
            'WH_ID'
        ].astype(str).unique()

        print("FCI IDs to remove:", len(FPS_ID))

        # ==========================================================
        # REMOVE FROM DISTANCE MATRIX
        # ==========================================================

        # Convert all column names to string
        DistanceBing.columns = DistanceBing.columns.astype(str)

        # If first column contains row IDs, convert to string
        DistanceBing.iloc[:, 0] = DistanceBing.iloc[:, 0].astype(str)

        # Remove warehouse columns
        Distance1 = DistanceBing.drop(
            columns=[col for col in DistanceBing.columns if col in Warehouse_ID],
            errors='ignore'
        )

        # Remove FCI rows
        Distance2 = Distance1[
            ~Distance1.iloc[:, 0].isin(FPS_ID)
        ]

        # ==========================================================
        # SAVE OUTPUT
        # ==========================================================
        with pd.ExcelWriter(
            'Backend//Bihar_Distance_L1.xlsx',
            engine='openpyxl'
        ) as writer:

            Distance2.to_excel(
                writer,
                sheet_name='BG_BG',
                index=False
            )

        print("Distance matrix updated successfully.")
        print("Final Shape:", Distance2.shape)
           
        Cost=pd.ExcelFile('Backend//Bihar_Distance_L1.xlsx')
        BG_BG = pd.read_excel(Cost,sheet_name="BG_BG")
        Cost.close()

        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)        

        Distance_BG_BG = {}
        column_list_BG_BG = list(BG_BG.columns)
        #print(column_list_BG_BG)
        row_list_BG_BG = list(BG_BG.iloc[:, 0])
        #print(row_list_BG_BG )  
        for ind in df5.index:
            from_code= df5['From_ID'][ind] 
            to_code = df5['To_ID'][ind]
            if to_code in row_list_BG_BG and from_code in column_list_BG_BG:
                index_i = row_list_BG_BG.index(to_code)
                index_j = column_list_BG_BG.index(from_code)
                key = str(to_code) + "_" + str(from_code)
                Distance_BG_BG[key]= BG_BG.iloc[index_i , index_j]
                #print(Distance_BG_BG[key])
            
        #df5["Tagging"]=df5['To_ID']+ '_' + df5['From_ID']
        df5["Tagging"] = df5['To_ID'].astype(str) + '_' + df5['From_ID'].astype(str)
        df5['Distance'] = df5['Tagging'].map(Distance_BG_BG)
        df5 = df5.replace('',pd.NaT).fillna('shallu')
        d5=df5.loc[df5['Distance'] == "shallu"]
        df5.to_excel('Backend//Result_Sheet12.xlsx',
                         sheet_name='Warehouse_FPS')

        
# ----------------------------------------------------------------------------------------------------------------------------------------------
        # ----------------------------------------------------------------------------------------------------------------------------------------------
# -------------------- READ INPUT --------------------
        Result_Sheet1 = pd.ExcelFile("Backend//Result_Sheet12.xlsx")
        df6 = pd.read_excel(Result_Sheet1, sheet_name="Warehouse_FPS")
        Result_Sheet1.close()

        df7 = df6.loc[df6['Distance'] == "shallu"].reset_index(drop=True)

        # -------------------- API Details --------------------
        auth_url = 'https://kerala.pmgatishakti.gov.in/DFPD/authenticate'
        distance_url = 'https://kerala.pmgatishakti.gov.in/PMGatishaktiApiService/dfpdapi/roaddistance'

        auth_payload = {
            "username": "DFPD_C",
            "password": "W9Vtb8WKkt3"
        }

        FILE_PATH = 'distanceIndent.json'

        # -------------------- Get Token --------------------
        def get_token():
            try:
                response = requests.post(auth_url, json=auth_payload, timeout=240)
                if response.status_code == 200:
                    return response.json().get('token')
                return None
            except requests.exceptions.RequestException as e:
                print("Auth API Error:", e)
                raise Exception("PMGatiShakti Authentication Service is currently unavailable. Please check your internet connection or try again later.")

        # -------------------- Batch API --------------------
        def process_batch(df_batch, token):
            headers = {'Authorization': f'Bearer {token}'}

            data = {
                "parameter": [{
                    "src_lng": row["From_Long"],
                    "src_lat": row["From_Lat"],
                    "dest_lng": row["To_Long"],
                    "dest_lat": row["To_Lat"]
                } for _, row in df_batch.iterrows()]
            }

            try:
                with open(FILE_PATH, 'w') as f:
                    json.dump(data, f, indent=4)

                with open(FILE_PATH, 'rb') as f:
                    files = {'LatsLongsFile': f}
                    response = requests.post(distance_url, headers=headers, files=files, timeout=240)

                return response
            except requests.exceptions.RequestException as e:
                print("Batch API Error:", e)
                raise Exception("PMGatiShakti Distance Service is currently unavailable. Please check your internet connection or try again later.")
            except Exception as e:
                print("Batch API Error:", e)
                return None

        # -------------------- Single Row API --------------------
        def process_single_row(row, token):
            headers = {'Authorization': f'Bearer {token}'}

            data = {
                "parameter": [{
                    "src_lng": row["From_Long"],
                    "src_lat": row["From_Lat"],
                    "dest_lng": row["To_Long"],
                    "dest_lat": row["To_Lat"]
                }]
            }

            try:
                with open(FILE_PATH, 'w') as f:
                    json.dump(data, f, indent=4)

                with open(FILE_PATH, 'rb') as f:
                    files = {'LatsLongsFile': f}
                    response = requests.post(distance_url, headers=headers, files=files, timeout=240)

                if response.status_code != 200:
                    return 0

                res_json = response.json()
                api_data = res_json.get("data", [])

                if len(api_data) == 0:
                    return 0

                distance = api_data[0].get("distance")

                if isinstance(distance, (int, float)):
                    return distance

                return 0
            except requests.exceptions.RequestException as e:
                print("Row API Error (Connection):", e)
                return "CONNECTION_ERROR"
            except Exception as e:
                print("Row API Error:", e)
                return 0

        # ----------------------------------------------------------------------------------------------------------------------------------------------
        # -------------------- MAIN PROCESS --------------------

        batch_size = 1000
        total_rows = len(df7)
        num_batches = (total_rows + batch_size - 1) // batch_size

        dist3 = []

        for batch_num in range(num_batches):
            print(f"Processing batch {batch_num+1}/{num_batches}")

            start_idx = batch_num * batch_size
            end_idx = min((batch_num + 1) * batch_size, total_rows)
            df_batch = df7.iloc[start_idx:end_idx]

            token = get_token()
            if not token:
                data_err = {"status": 0, "message": "Failed to retrieve PMGatiShakti token."}
                return json.dumps(data_err, indent=1)

            response = process_batch(df_batch, token)

            fallback_required = False

            if not response or response.status_code != 200:
                fallback_required = True
            else:
                try:
                    response_json = response.json()
                    api_data = response_json.get("data", [])

                    if len(api_data) != len(df_batch):
                        fallback_required = True
                    else:
                        for row_data in api_data:
                            distance = row_data.get("distance")
                            if not isinstance(distance, (int, float)):
                                fallback_required = True
                                break

                except Exception:
                    fallback_required = True

            # ---------------- FALLBACK ----------------
            if fallback_required:
                print(f"Batch {batch_num+1} failed -> switching to row-wise")

                for _, row in df_batch.iterrows():
                    distance = process_single_row(row, token)
                    if distance == "CONNECTION_ERROR":
                        data_err = {"status": 0, "message": "PMGatiShakti API is currently unavailable or there is an internet connection issue. Please check your connection and try again."}
                        return json.dumps(data_err, indent=1)

                    if distance == 0:
                        print(f"Distance set to 0 for From {row['From_ID']} -> To {row['To_ID']}")

                    dist3.append(distance)

            # ---------------- NORMAL ----------------
            else:
                for row_data in api_data:
                    dist3.append(row_data.get("distance"))

        

        # -------------------- UPDATE DATA --------------------
        df7["Distance"] = dist3

        df9 = df6.loc[df6['Distance'] != "shallu"]

        columns = [
            'Scenario','From','From_State','From_District','From_ID','From_Name',
            'From_Lat','From_Long','To','To_ID','To_Name','To_State','To_District',
            'To_Lat','To_Long','commodity','quantity','Distance'
        ]

        df9 = df9[columns]
        df7 = df7[columns]

        df10 = pd.concat([df9, df7], ignore_index=True)

        # -------------------- FINAL RESULT --------------------
        result = (df10['quantity'] * df10['Distance']).sum()

        print("Total Result:", result)

        # -------------------- SAVE OUTPUT --------------------
        df10.to_excel('Backend//Result_Sheet_leg1.xlsx', sheet_name='Warehouse_FPS', index=False)

        print("Process Completed Successfully")
# ---------------------------------------------
                     
        Total_Demand=  float(WH['Allocation_Wheat'].sum()) + float(WH['Allocation_Rice'].sum())  

        data ={}        
        
        data["Scenario"]="Inter"
        data["Scenario_Baseline"] = "Baseline"
        
        data["WH_Used"] = df5['From_ID'].nunique()
        data["WH_Used_Baseline"] = "5"
        
        data["FPS_Used"] = df5['To_ID'].nunique()
        data["FPS_Used_Baseline"] = "76"
        
        data['Demand'] = Total_Demand
        data['Demand_Baseline'] = "69,247"
        
        
        
        data['Total_QKM'] = float(result)
        data['Total_QKM_Baseline'] = "67,16,263"
        
        data['Average_Distance'] = float(round(result, 2)) / Total_Demand
        data['Average_Distance_Baseline'] = "96.66"

        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)                     
        
        save_to_database_leg1(month, year, applicable, scenario_type)
        save_monthly_data_leg1(month, year, float(result))
        
        def delete_files(file_paths):
            for file_path in file_paths:
                try:
                    if os.path.exists(file_path):  # Check if the file exists
                        os.remove(file_path)  # Delete the file
                        #print(f"{file_path} has been deleted.")
                    else:
                        print(f"{file_path} does not exist.")
                except Exception as e:
                    print(f"Error deleting {file_path}: {e}")

        # List of files to delete
        files_to_delete = [
            'Backend/Bihar_Distance_L1.xlsx',
            'Backend/Result_Sheet12.xlsx',
            'Backend//Tagging_Sheet_Pre11.xlsx',
            'Backend/Tagging_Sheet_Pre11_leg1.xlsx',
            
        ]

        # Call the function to delete the files
        delete_files(files_to_delete)
		
        
        json_data = json.dumps(data)
        json_object = json.loads(json_data)

        if os.path.exists('ouputPickle.pkl'):
            os.remove('ouputPickle.pkl')

        # open pickle file
        dbfile1 = open('ouputPickle.pkl', 'ab')
    else:
        message = 'DataFile file is incorrect'
        try:
            USN = pd.ExcelFile('Backend//Data_2.xlsx')
            month = request.form.get('month')        
            year = request.form.get('year')
            scenario_type = request.form.get('type')
            applicable = request.form.get('applicable')
        except Exception as e:
            data = {}
            data['status'] = 0
            data['message'] = message
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)
        input = pd.ExcelFile('Backend//Data_2.xlsx')
        node1 = pd.read_excel(input,sheet_name="A.2 FCI")
        node2 = pd.read_excel(input,sheet_name="A.1 Warehouse")
        node3 = pd.read_excel(input,sheet_name="A.2 DCP")

        dist = [[0 for a in range(len(node2["SW_ID"]))] for b in range(len(node1["WH_ID"]))]
        phi_1 = []
        phi_2 = []
        delta_phi = []
        delta_lambda = []
        R = 6371 

        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)

        for i in node1.index:
            for j in node2.index:
                phi_1=math.radians(node1["WH_Lat"][i])
                phi_2=math.radians(node2["SW_lat"][j])
                delta_phi=math.radians(node2["SW_lat"][j]-node1["WH_Lat"][i])
                delta_lambda=math.radians(node2["SW_Long"][j]-node1["WH_Long"][i])
                x=math.sin(delta_phi / 2.0) ** 2 + math.cos(phi_1) * math.cos(phi_2) * math.sin(delta_lambda / 2.0) ** 2
                y=2 * math.atan2(math.sqrt(x), math.sqrt(1 - x))
                dist[i][j]=R*y
                
       


        dist1 = [[0 for a in range(len(node2["SW_ID"]))] for b in range(len(node3["WH_ID"]))]
        phi_11 = []
        phi_21 = []
        delta_phi1 = []
        delta_lambda1 = []
        R = 6371 

        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)

        for i in node3.index:
            for j in node2.index:
                phi_11=math.radians(node3["WH_Lat"][i])
                phi_21=math.radians(node2["SW_lat"][j])
                delta_phi1=math.radians(node2["SW_lat"][j]-node3["WH_Lat"][i])
                delta_lambda1=math.radians(node2["SW_Long"][j]-node3["WH_Long"][i])
                x=math.sin(delta_phi1 / 2.0) ** 2 + math.cos(phi_11) * math.cos(phi_21) * math.sin(delta_lambda1 / 2.0) ** 2
                y=2 * math.atan2(math.sqrt(x), math.sqrt(1 - x))
                dist1[i][j]=R*y
                
        
        FCI = pd.read_excel(USN, sheet_name='A.2 FCI', index_col=None)
        WH = pd.read_excel(USN, sheet_name='A.1 Warehouse', index_col=None)
        DCP = pd.read_excel(USN, sheet_name='A.2 DCP', index_col=None)

        if 'Rice' in WH.columns:
            WH['Allocation_Rice'] = WH['Rice']
        if 'Wheat' in WH.columns:
            WH['Allocation_Wheat'] = WH['Wheat']

        FCI['WH_District'] = FCI['WH_District'].apply(lambda x: x.replace(' ', ''))
        WH['SW_District'] = WH['SW_District'].apply(lambda x: x.replace(' ', ''))
        DCP['WH_District'] = DCP['WH_District'].apply(lambda x: x.replace(' ', ''))
        
        
        excel_path = "Backend//Distance_Intial_L1.xlsx"
        output_path = "Backend//Distance_Initial_L1_updated.xlsx"
        sheet_name = "BG_BG"
        excel_password = "distf"

        # ---------- Step 1: Get latest optimisation table ---------- #
        conn = connect_to_database()
        cursor = conn.cursor(dictionary=True)

        cursor.execute("""
            SELECT id
            FROM optimised_table_leg1
            WHERE month = %s and year = %s
            ORDER BY last_updated DESC
            LIMIT 1
        """, (month, year))
        opt = cursor.fetchone()

        updates = []
        if opt:
            table_name = f"optimiseddata_leg1_{opt['id']}"
            cursor.execute("SHOW TABLES LIKE %s", (table_name,))
            table_exists = cursor.fetchone()
            if table_exists:
                cursor.execute(f"""
                    SELECT from_id, to_id, new_distance_district, approve_district
                    FROM `{table_name}`
                    WHERE LOWER(approve_district) = 'no'
                """)
                updates = cursor.fetchall()

        cursor.close()
        conn.close()

        if updates:
            # ---------- Step 2: Decrypt Excel ---------- #
            decrypted = io.BytesIO()
            with open(excel_path, "rb") as f:
                office = msoffcrypto.OfficeFile(f)
                office.load_key(password=excel_password)
                office.decrypt(decrypted)

            decrypted.seek(0)

            # ---------- Step 3: Read Excel and Parse All Sheets ---------- #
            xl = pd.ExcelFile(decrypted, engine="openpyxl")
            sheets = {name: xl.parse(name) for name in xl.sheet_names}
            df = sheets[sheet_name]

            df.rename(columns={df.columns[0]: "to_id"}, inplace=True)
            df["to_id"] = df["to_id"].astype(str)
            df.set_index("to_id", inplace=True)

            df.columns = df.columns.astype(str)

            # ---------- Step 4: Intelligent Update + Minimal Append ---------- #
            updated_cells = 0
            appended_routes = 0

            for row in updates:
                from_id = str(row["from_id"])
                to_id = str(row["to_id"])
                new_dist = row.get("new_distance_district")
                if new_dist is not None:
                    try:
                        distance = float(new_dist)
                        if distance > 0:
                            # ---- Ensure ROW exists ---- #
                            if to_id not in df.index:
                                df.loc[to_id] = 0
                                appended_routes += 1

                            # ---- Ensure COLUMN exists ---- #
                            if from_id not in df.columns:
                                df[from_id] = 0
                                appended_routes += 1

                            # ---- Update the specific cell ---- #
                            if df.at[to_id, from_id] != distance:
                                df.at[to_id, from_id] = distance
                                updated_cells += 1
                    except (ValueError, TypeError):
                        pass

            # ---------- Step 5: Save Excel with All Sheets and Encrypt ---------- #
            output_path = "Backend//Distance_Initial_L1_updated.xlsx"
            sheets[sheet_name] = df.reset_index()

            plain_buf = io.BytesIO()
            with pd.ExcelWriter(plain_buf, engine="xlsxwriter") as writer:
                for name, sheet_df in sheets.items():
                    sheet_df.to_excel(writer, sheet_name=name, index=False)
            plain_buf.seek(0)

            file = msoffcrypto.format.ooxml.OOXMLFile(plain_buf)
            with open(output_path, "wb") as f_out:
                file.encrypt(excel_password, f_out)
        else:
            import shutil
            shutil.copy(excel_path, output_path)

        
        model = LpProblem('Supply-Demand-Problem', LpMinimize)

        Variable3 = []
        
        for i in range(len(DCP['WH_ID'])):
            for j in range(len(WH['SW_ID'])):
                Variable3.append(str(DCP['WH_ID'][i]) + '_'
                                 + str(DCP['WH_District'][i]) + '_'
                                 + str(WH['SW_ID'][j]) + '_'
                                 + str(WH['SW_District'][j]) + f'_FRice_{i}_{j}')
                                 
        Variable4 = []
        for i in range(len(FCI['WH_ID'])):
            for j in range(len(WH['SW_ID'])):
                Variable4.append(str(FCI['WH_ID'][i]) + '_'
                                 + str(FCI['WH_District'][i]) + '_'
                                 + str(WH['SW_ID'][j]) + '_'
                                 + str(WH['SW_District'][j]) + f'_Wheat_{i}_{j}')
                                 
                                 
        Variable5 = []
        
        for i in range(len(DCP['WH_ID'])):
            for j in range(len(WH['SW_ID'])):
                Variable5.append(str(DCP['WH_ID'][i]) + '_'
                                 + str(DCP['WH_District'][i]) + '_'
                                 + str(WH['SW_ID'][j]) + '_'
                                 + str(WH['SW_District'][j]) + f'_Wheat_{i}_{j}')
                                 
        Variable6 = []
        for i in range(len(FCI['WH_ID'])):
            for j in range(len(WH['SW_ID'])):
                Variable6.append(str(FCI['WH_ID'][i]) + '_'
                                 + str(FCI['WH_District'][i]) + '_'
                                 + str(WH['SW_ID'][j]) + '_'
                                 + str(WH['SW_District'][j]) + f'_FRice_{i}_{j}')                         

        # Variables for Wheat from lEVEL2 TO FPS

        DV_Variables3 = LpVariable.matrix('X', Variable3, cat='float',
                lowBound=0)
        Allocation3 = np.array(DV_Variables3).reshape(len(DCP['WH_ID']),
                len(WH['SW_ID']))
                
        
                
        DV_Variables4 = LpVariable.matrix('Y', Variable4, cat='float',
                lowBound=0)
        Allocation4 = np.array(DV_Variables4).reshape(len(FCI['WH_ID']),
                len(WH['SW_ID']))

        DV_Variables5 = LpVariable.matrix('Y', Variable5, cat='float',
                lowBound=0)
        Allocation5 = np.array(DV_Variables5).reshape(len(DCP['WH_ID']),
                len(WH['SW_ID']))
                
        DV_Variables6 = LpVariable.matrix('Y', Variable6, cat='float',
                lowBound=0)
        Allocation6 = np.array(DV_Variables6).reshape(len(FCI['WH_ID']),
                len(WH['SW_ID']))
        
        State_Riceprocurement = {}
        for i in range(len(DCP["WH_District"])):
            District_Name = DCP["WH_District"][i]
            if District_Name not in State_Riceprocurement:
                State_Riceprocurement[District_Name] = float(DCP["Procurement Rice"][i])
            else:
                State_Riceprocurement[District_Name] += float(DCP["Procurement Rice"][i])
           

        District_DemandRice = {}
        for i in range(len(WH["SW_District"])):
            District_Name_FPS = WH["SW_District"][i]
            if District_Name_FPS not in District_DemandRice:
                District_DemandRice[District_Name_FPS] = float(WH["Demand_Rice"][i])
            else:
                District_DemandRice[District_Name_FPS] += float(WH["Demand_Rice"][i]) 
        
        District_Name = []
        District_Name2=[]
        District_Name = [i for i in District_DemandRice if i not in State_Riceprocurement]
        District_Name4 = [i for i in State_Riceprocurement if i not in District_DemandRice]
        District_Name2 = [i for i in District_DemandRice if i in State_Riceprocurement and District_DemandRice[i] >= State_Riceprocurement[i]]
        District_Name_1 = {}
        District_Name_1['District_Name_All'] = District_Name + District_Name2
        District_Name3 = [i for i in District_DemandRice if i in State_Riceprocurement and District_DemandRice[i] <= State_Riceprocurement[i]]
        
        
        name1 = []
        lst1 = []
        for j in range(len(DV_Variables3)):
            name1 = str(DV_Variables3[j])
            lst1 = name1.split("_")
            if lst1[2] in District_Name3 and lst1[4] in District_Name3 and lst1[2]!=lst1[4]:
                model+=DV_Variables3[j]==0
                #print(DV_Variables1[j]==0)
                
        name2 = []
        lst2 = []
        for j in range(len(DV_Variables3)):
            name2 = str(DV_Variables3[j])
            lst2 = name2.split("_")
            if lst2[2] in District_Name2 and lst2[4] in District_Name3:
                model+=DV_Variables3[j]==0
                #print(DV_Variables3[j]==0)
                
        name3 = []
        lst3 = []
        for j in range(len(DV_Variables3)):
            name3 = str(DV_Variables3[j])
            lst3 = name3.split("_")
            if lst3[2] in District_Name2 and lst3[4] in District_Name2 and lst3[2]!=lst3[4]:
                model+=DV_Variables3[j]==0
                #print(DV_Variables1[j]==0)

        name4 = []
        lst4 = []
        for j in range(len(DV_Variables3)):
            name4 = str(DV_Variables3[j])
            lst4 = name4.split("_")
            if lst4[2] in District_Name4 and lst4[4] in District_Name3:
                model+=DV_Variables3[j]==0
                #print(DV_Variables1[j]==0)

        District_Capacity = {}
        for i in range(len(FCI["WH_District"])):
            District_Name = FCI["WH_District"][i]
            if District_Name not in District_Capacity:
                District_Capacity[District_Name] = float(FCI["Allotment_Rice"][i])
            else:
                District_Capacity[District_Name] += float(FCI["Allotment_Rice"][i])
                
        District_DemandWheat = {}
        for i in range(len(WH["SW_District"])):
            District_Name_FPS = WH["SW_District"][i]
            if District_Name_FPS not in District_DemandWheat :
                District_DemandWheat [District_Name_FPS] = float(WH["Demand_Wheat"][i]) + float(WH["Demand_Rice"][i])
            else:
                District_DemandWheat [District_Name_FPS] += float(WH["Demand_Wheat"][i]) + float(WH["Demand_Rice"][i])
                
                
        District_Name_wheat = []
        District_Name2_wheat=[]
        District_Name_wheat= [i for i in District_DemandWheat if i not in District_Capacity]
        District_Name4_wheat = [i for i in District_Capacity if i not in District_DemandWheat]
        District_Name2_wheat = [i for i in District_DemandWheat if i in District_Capacity and District_DemandWheat[i] >= District_Capacity[i]]
        District_Name_1_wheat = {}
        District_Name_1_wheat['District_Name_All'] = District_Name_wheat + District_Name2_wheat
        District_Name3_wheat = [i for i in District_DemandWheat if i in District_Capacity and District_DemandWheat[i] <= District_Capacity[i]]
        
        
        name5 = []
        lst5 = []
        for j in range(len(DV_Variables4)):
            name5 = str(DV_Variables4[j])
            lst5 = name5.split("_")
            if lst5[2] in District_Name3_wheat and lst5[4] in District_Name3_wheat and lst5[2]!=lst5[4]:
                model+=DV_Variables4[j]==0
                #print(DV_Variables1[j]==0)
                
        name6 = []
        lst6 = []
        for j in range(len(DV_Variables4)):
            name6 = str(DV_Variables4[j])
            lst6 = name6.split("_")
            if lst6[2] in District_Name2_wheat and lst6[4] in District_Name3_wheat:
                model+=DV_Variables4[j]==0
                #print(DV_Variables1[j]==0)
                
        name7 = []
        lst7 = []
        for j in range(len(DV_Variables4)):
            name7 = str(DV_Variables4[j])
            lst7 = name7.split("_")
            if lst7[2] in District_Name2_wheat and lst7[4] in District_Name2_wheat and lst7[2]!=lst7[4]:
                model+=DV_Variables4[j]==0
                #print(DV_Variables1[j]==0)

        name8 = []
        lst8 = []
        for j in range(len(DV_Variables4)):
            name8 = str(DV_Variables4[j])
            lst8 = name8.split("_")
            if lst8[2] in District_Name4_wheat and lst8[4] in District_Name3_wheat:
                model+=DV_Variables4[j]==0
                #print(DV_Variables1[j]==0)
                
                
        name13 = []
        lst13 = []
        for j in range(len(DV_Variables6)):
            name13 = str(DV_Variables6[j])
            lst13 = name13.split("_")
            if lst13[2] in District_Name3_wheat and lst13[4] in District_Name3_wheat and lst13[2]!=lst13[4]:
                model+=DV_Variables6[j]==0
                #print(DV_Variables1[j]==0)
                
        name14 = []
        lst14 = []
        for j in range(len(DV_Variables6)):
            name14 = str(DV_Variables6[j])
            lst14 = name14.split("_")
            if lst14[2] in District_Name2_wheat and lst14[4] in District_Name3_wheat:
                model+=DV_Variables6[j]==0
                #print(DV_Variables1[j]==0)
                
        name15 = []
        lst15 = []
        for j in range(len(DV_Variables6)):
            name15 = str(DV_Variables6[j])
            lst15 = name15.split("_")
            if lst15[2] in District_Name2_wheat and lst15[4] in District_Name2_wheat and lst15[2]!=lst15[4]:
                model+=DV_Variables6[j]==0
                #print(DV_Variables1[j]==0)

        name16 = []
        lst16 = []
        for j in range(len(DV_Variables6)):
            name16 = str(DV_Variables6[j])
            lst16 = name16.split("_")
            if lst16[2] in District_Name4_wheat and lst16[4] in District_Name3_wheat:
                model+=DV_Variables6[j]==0
                #print(DV_Variables1[j]==0)        
                
        State_Wheatprocurement = {}
        for i in range(len(DCP["WH_District"])):
            District_Name = DCP["WH_District"][i]
            if District_Name not in State_Wheatprocurement:
                State_Wheatprocurement[District_Name] = float(DCP["Procurement Wheat"][i])
            else:
                State_Wheatprocurement[District_Name] += float(DCP["Procurement Wheat"][i])
                
                
                
        District_DemandWheat1 = {}
        for i in range(len(WH["SW_District"])):
            District_Name_FPS = WH["SW_District"][i]
            if District_Name_FPS not in District_DemandWheat1 :
                District_DemandWheat1 [District_Name_FPS] = float(WH["Allocation_Wheat"][i])
            else:
                District_DemandWheat1 [District_Name_FPS] += float(WH["Allocation_Wheat"][i])

        
        District_Name_wheat1 = []
        District_Name2_wheat1=[]
        District_Name_wheat1= [i for i in District_DemandWheat1 if i not in State_Wheatprocurement]
        District_Name4_wheat1 = [i for i in State_Wheatprocurement if i not in District_DemandWheat1]
        District_Name2_wheat1 = [i for i in District_DemandWheat1 if i in State_Wheatprocurement and District_DemandWheat1[i] >= State_Wheatprocurement[i]]
        District_Name_1_wheat1 = {}
        District_Name_1_wheat1['District_Name_All'] = District_Name_wheat + District_Name2_wheat
        District_Name3_wheat1 = [i for i in District_DemandWheat1 if i in State_Wheatprocurement and District_DemandWheat1[i] <= State_Wheatprocurement[i]]
        
        
        name9 = []
        lst9 = []
        for j in range(len(DV_Variables5)):
            name9 = str(DV_Variables5[j])
            lst9 = name9.split("_")
            if lst9[2] in District_Name3_wheat1 and lst9[4] in District_Name3_wheat1 and lst9[2]!=lst9[4]:
                model+=DV_Variables5[j]==0
                #print(DV_Variables1[j]==0)
                
        name10 = []
        lst10 = []
        for j in range(len(DV_Variables5)):
            name10 = str(DV_Variables5[j])
            lst10 = name10.split("_")
            if lst10[2] in District_Name2_wheat1 and lst10[4] in District_Name3_wheat1:
                model+=DV_Variables5[j]==0
                #print(DV_Variables1[j]==0)
                
        name11 = []
        lst11 = []
        for j in range(len(DV_Variables5)):
            name11 = str(DV_Variables5[j])
            lst11 = name11.split("_")
            if lst11[2] in District_Name2_wheat1 and lst11[4] in District_Name2_wheat1 and lst11[2]!=lst11[4]:
                model+=DV_Variables5[j]==0
                #print(DV_Variables1[j]==0)

        name12 = []
        lst12 = []
        for j in range(len(DV_Variables5)):
            name12 = str(DV_Variables5[j])
            lst12 = name12.split("_")
            if lst12[2] in District_Name4_wheat1 and lst12[4] in District_Name3_wheat1:
                model+=DV_Variables5[j]==0
                #print(DV_Variables1[j]==0)
        
        
        
        
        


        allCombination3 = []
        allCombination4 = []
        allCombination5 = []
        allCombination6 = []


        for i in range(len(dist1)):
            for j in range(len(WH['SW_ID'])):
                allCombination3.append(Allocation3[i][j] * dist1[i][j])
        
        for i in range(len(dist1)):
            for j in range(len(WH['SW_ID'])):
                allCombination5.append(Allocation5[i][j] * dist1[i][j])
                
        for i in range(len(dist)):
            for j in range(len(WH['SW_ID'])):
                allCombination4.append(Allocation4[i][j] * dist[i][j])
                
        for i in range(len(dist)):
            for j in range(len(WH['SW_ID'])):
                allCombination6.append(Allocation6[i][j] * dist[i][j])        

        model += lpSum(allCombination3 + allCombination4 + allCombination5+ allCombination6)
        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)

        # Demand Constraints for Wheat

        for i in range(len(WH['SW_ID'])):
            model += ((lpSum(Allocation4[j][i] for j in range(len(FCI['WH_ID'
                           ])))) + (lpSum(Allocation5[j][i] for j in range(len(DCP['WH_ID'
                           ])))) >= WH['Allocation_Wheat'][i])
                           
        for i in range(len(WH['SW_ID'])):
            model += ((lpSum(Allocation4[j][i] for j in range(len(FCI['WH_ID'
                           ])))) + (lpSum(Allocation5[j][i] for j in range(len(DCP['WH_ID'
                           ])))) <= WH['Allocation_Wheat'][i])
                           
        for i in range(len(WH['SW_ID'])):
            model += (lpSum(Allocation3[j][i] for j in range(len(DCP['WH_ID'
                           ]))) +(lpSum(Allocation6[j][i] for j in range(len(FCI['WH_ID'
                           ])))>= WH['Allocation_Rice'][i]))
        
        for i in range(len(WH['SW_ID'])):
            model += (lpSum(Allocation3[j][i] for j in range(len(DCP['WH_ID'
                           ]))) +(lpSum(Allocation6[j][i] for j in range(len(FCI['WH_ID'
                           ])))<= WH['Allocation_Rice'][i]))

        # Supply Constraints for Warehouses

        for i in range(len(DCP['WH_ID'])):
            model += ((lpSum(Allocation3[i][j] for j in range(len(WH['SW_ID'
                           ]))))  <= DCP['Procurement Rice'][i])
        
        for i in range(len(DCP['WH_ID'])):
            model += ((lpSum(Allocation3[i][j] for j in range(len(WH['SW_ID'
                           ]))))  >= DCP['Procurement Rice'][i])
                           
        for i in range(len(FCI['WH_ID'])):
            model += ((lpSum(Allocation4[i][j] for j in range(len(WH['SW_ID'
                           ])))) + (lpSum(Allocation6[i][j] for j in range(len(WH['SW_ID'
                           ])))) <= FCI['Storage_Capacity'][i])
                                           
        for i in range(len(DCP['WH_ID'])):
            model += ((lpSum(Allocation5[i][j] for j in range(len(WH['SW_ID'
                           ]))))  >= DCP['Procurement Wheat'][i])
                           
        for i in range(len(DCP['WH_ID'])):
            model += ((lpSum(Allocation5[i][j] for j in range(len(WH['SW_ID'
                           ]))))  <= DCP['Procurement Wheat'][i])                  
        
        
        
        
        
        


       # Calling CBC_CMB Solver
        

        
        model.solve(COIN_CMD(path=get_cbc_path(),msg=0,gapRel=0.03,timeLimit=600))

        
        status = LpStatus[model.status]

        if status != "Optimal":
            print("Optimization failed:", status)

            data = {
                "status": 0,
                "message": "Infeasible or Unbounded Solution"
            }

            return json.dumps(data, indent=1)

        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)

        
        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)        
        
        

        Original_Cost = 100000000
        total = Original_Cost

        data = {}
       

        
        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)

        Output_File = open('Backend//Inter_District1_leg1.csv', 'w')
        for v in model.variables():
            if v.value() > 0:
                Output_File.write(v.name + '\t' + str(v.value()) + '\n')

        Output_File = open('Backend//Inter_District1_leg1.csv', 'w')
        for v in model.variables():
            if v.value() > 0:
                Output_File.write(v.name + '\t' + str(v.value()) + '\n')

        df9 = pd.read_csv('Backend//Inter_District1_leg1.csv',header=None)

        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)        

        df9.columns = ['Tagging']
        df9[[
            'Var',
            'WH_ID',
            'W_D',
            'SW_ID',
            'SW_D',
            'commodity_Value',
            ]] = df9[df9.columns[0]].str.split('_', n=5, expand=True)
        del df9[df9.columns[0]]
        df9[['commodity', 'Values']] = df9['commodity_Value'].str.split('\\t', n=1, expand=True)
        del df9['commodity_Value']
        df9['commodity'] = df9['commodity'].str.split('_').str[0]
        
        df9 = df9.drop(np.where(df9['commodity'] == 'Wheat1')[0])
        def convert_to_numeric(value):
            try:
                return pd.to_numeric(value)
            except ValueError:
                return value
        
        
        df9['WH_ID'] = df9['WH_ID'].apply(convert_to_numeric)
        df9['SW_ID'] = df9['SW_ID'].apply(convert_to_numeric)
        
        df9.to_excel('Backend//Tagging_Sheet_Pre_leg1.xlsx', sheet_name='BG_FPS')
        df31 = pd.read_excel('Backend//Tagging_Sheet_Pre_leg1.xlsx')
        
        USN = pd.ExcelFile('Backend//Data_2.xlsx')
        WH = pd.read_excel(USN, sheet_name='A.1 Warehouse', index_col=None)
        FCI = pd.read_excel(USN, sheet_name='A.2 FCI', index_col=None)        # Convert to object type, adjust as needed
        DCP = pd.read_excel(USN, sheet_name='A.2 DCP', index_col=None)  
        
        columns_to_include = ["WH_District","WH_Name","WH_ID",	"Type of WH",	"WH_Lat",	"WH_Long"]
        df1_selected = FCI[columns_to_include]
        df2_selected = DCP[columns_to_include]
        
        FCI = pd.concat([df1_selected, df2_selected], ignore_index=True)

        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)
        


        df4 = pd.merge(df31, FCI, on='WH_ID', how='inner')
        #df4 = pd.merge(df31, FCI, on='WH_ID', how='inner')
        df4 = df4[[
            'WH_ID',
            'WH_Name',
            'WH_District',
            'WH_Lat',
            'WH_Long',
            'Type of WH',
            'SW_ID',
            'commodity',
            'Values',
            ]]
        df4 = pd.merge(df4, WH, on='SW_ID', how='inner')
        df51 = df4[[
            'WH_ID',
            'WH_Name',
            'WH_District',
            'WH_Lat',
            'WH_Long',
            'Type of WH',
            'SW_ID',
            'SW_Name',
            'SW_District',
            'SW_lat',
            'SW_Long',
            'commodity',
            'Values',
            ]]
        df51.insert(0, 'Scenario', 'Optimized')
        df51.insert(2, 'From_State', 'Bihar')
        df51.insert(7, 'To', 'TPDS')
        df51.insert(8, 'To_State', 'Bihar')
        
        df51.rename(columns={
            'WH_ID': 'From_ID',
            'WH_Name': 'From_Name',
            'WH_Lat': 'From_Lat',
            'Type of WH': 'From',
            'WH_Long': 'From_Long',
            }, inplace=True)
        df51.rename(columns={
            'SW_ID': 'To_ID',
            'SW_Name': 'To_Name',
            'SW_lat': 'To_Lat',
            'SW_Long': 'To_Long',
            'Values':'quantity',
            }, inplace=True)
        df51.rename(columns={'WH_District': 'From_District',
                   'SW_District': 'To_District'}, inplace=True)
        df51 = df51.loc[:, [
            'Scenario',
            'From',
            'From_State',
            'From_District',
            'From_ID',
            'From_Name',
            'From_Lat',
            'From_Long',
            'To',
            'To_ID',
            'To_Name',
            'To_State',
            'To_District',
            'To_Lat',
            'To_Long',
            'commodity',
            'quantity',
            ]]
            
        def convert_to_numeric(value):
            try:
                return pd.to_numeric(value)
            except ValueError:
                return value
                
        
        df51['From_ID'] = df51['From_ID'].apply(convert_to_numeric)
        df51['To_ID'] = df51['To_ID'].apply(convert_to_numeric)   
        
        df51.to_excel('Backend//Tagging_Sheet_Pre11_leg1.xlsx', sheet_name='BG_FPS1')
        data1 = pd.ExcelFile("Backend//Tagging_Sheet_Pre11_leg1.xlsx")
        df5 = pd.read_excel(data1,sheet_name="BG_FPS1")
        data1.close()
        
        
        
        
        # ==========================================================
        # READ MASTER DATA
        # ==========================================================
        input_file = pd.ExcelFile('Backend//Data_2.xlsx')

        # Warehouse Sheet
        node1 = pd.read_excel(input_file, sheet_name="A.1 Warehouse")

        node1['SW_ID'] = node1['SW_ID'].astype(str).str.strip()

        node1['Lat_Long_r'] = (
            node1[['SW_lat', 'SW_Long']]
            .astype(float)
            .round(3)
            .astype(str)
            .agg(','.join, axis=1)
        )

        # FCI Sheet
        node2 = pd.read_excel(input,sheet_name="A.2 FCI")
        node3 = pd.read_excel(input,sheet_name="A.2 DCP")
        columns_to_include = ["WH_District","WH_Name","WH_ID",	"Type of WH",	"WH_Lat",	"WH_Long"]
        df1_selected = node2[columns_to_include]
        df2_selected = node3[columns_to_include]
        
        node2 = pd.concat([df1_selected, df2_selected], ignore_index=True)

        node2['WH_ID'] = node2['WH_ID'].astype(str).str.strip()

        node2['Lat_Long_r'] = (
            node2[['WH_Lat', 'WH_Long']]
            .astype(float)
            .round(3)
            .astype(str)
            .agg(','.join, axis=1)
        )

        
        updated_excel_path = 'Backend//Distance_Initial_L1_updated.xlsx'
        ref_excel_path = updated_excel_path if os.path.exists(updated_excel_path) else 'Backend//Distance_Intial_L1.xlsx'
        DistanceBing = read_protected_excel(ref_excel_path, 'distf', sheet_name='BG_BG')
        Warehouse = read_protected_excel(ref_excel_path, 'distf', sheet_name='Warehouse')
        FCI = read_protected_excel(ref_excel_path, 'distf', sheet_name='FCI')

        # ==========================================================
        # STANDARDIZE IDS
        # ==========================================================
        Warehouse['SW_ID'] = Warehouse['SW_ID'].astype(str).str.strip()
        FCI['WH_ID'] = FCI['WH_ID'].astype(str).str.strip()

        # ==========================================================
        # ROUND LAT LONG IN DISTANCE FILE
        # ==========================================================
        Warehouse['Lat_Long_r'] = (
            Warehouse['Lat_Long']
            .astype(str)
            .str.split(',', expand=True)
            .astype(float)
            .round(3)
            .astype(str)
            .agg(','.join, axis=1)
        )

        FCI['Lat_Long_r'] = (
            FCI['Lat_Long']
            .astype(str)
            .str.split(',', expand=True)
            .astype(float)
            .round(3)
            .astype(str)
            .agg(','.join, axis=1)
        )

        # ==========================================================
        # FIND WAREHOUSES WITH CHANGED LAT LONG
        # ==========================================================
        War = pd.merge(
            node1[['SW_ID', 'Lat_Long_r']],
            Warehouse[['SW_ID', 'Lat_Long_r']],
            on='SW_ID',
            how='inner',
            suffixes=('_master', '_distance')
        )

        Warehouse_ID = War.loc[
            War['Lat_Long_r_master'] != War['Lat_Long_r_distance'],
            'SW_ID'
        ].astype(str).unique()

        print("Warehouse IDs to remove:", len(Warehouse_ID))

        # ==========================================================
        # FIND FCI WITH CHANGED LAT LONG
        # ==========================================================
        FPS1 = pd.merge(
            node2[['WH_ID', 'Lat_Long_r']],
            FCI[['WH_ID', 'Lat_Long_r']],
            on='WH_ID',
            how='inner',
            suffixes=('_master', '_distance')
        )

        FPS_ID = FPS1.loc[
            FPS1['Lat_Long_r_master'] != FPS1['Lat_Long_r_distance'],
            'WH_ID'
        ].astype(str).unique()

        print("FCI IDs to remove:", len(FPS_ID))

        # ==========================================================
        # REMOVE FROM DISTANCE MATRIX
        # ==========================================================

        # Convert all column names to string
        DistanceBing.columns = DistanceBing.columns.astype(str)

        # If first column contains row IDs, convert to string
        DistanceBing.iloc[:, 0] = DistanceBing.iloc[:, 0].astype(str)

        # Remove warehouse columns
        Distance1 = DistanceBing.drop(
            columns=[col for col in DistanceBing.columns if col in Warehouse_ID],
            errors='ignore'
        )

        # Remove FCI rows
        Distance2 = Distance1[
            ~Distance1.iloc[:, 0].isin(FPS_ID)
        ]

        # ==========================================================
        # SAVE OUTPUT
        # ==========================================================
        with pd.ExcelWriter(
            'Backend//Bihar_Distance_L1.xlsx',
            engine='openpyxl'
        ) as writer:

            Distance2.to_excel(
                writer,
                sheet_name='BG_BG',
                index=False
            )

        print("Distance matrix updated successfully.")
        print("Final Shape:", Distance2.shape)

        
        Cost=pd.ExcelFile('Backend//Bihar_Distance_L1.xlsx')
        BG_BG = pd.read_excel(Cost,sheet_name="BG_BG")
        Cost.close()
        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)        

        Distance_BG_BG = {}
        column_list_BG_BG = list(BG_BG.columns)
        #print(column_list_BG_BG)
        row_list_BG_BG = list(BG_BG.iloc[:, 0])
        #print(row_list_BG_BG )  
        for ind in df5.index:
            from_code= df5['From_ID'][ind] 
            to_code = df5['To_ID'][ind]
            if to_code in row_list_BG_BG and from_code in column_list_BG_BG:
                index_i = row_list_BG_BG.index(to_code)
                index_j = column_list_BG_BG.index(from_code)
                key = str(to_code) + "_" + str(from_code)
                Distance_BG_BG[key]= BG_BG.iloc[index_i , index_j]
                
            
        #df5["Tagging"]=df5['To_ID']+ '_' + df5['From_ID']
        df5["Tagging"] = df5['To_ID'].astype(str) + '_' + df5['From_ID'].astype(str)
        df5['Distance'] = df5['Tagging'].map(Distance_BG_BG)
        df5 = df5.replace('',pd.NaT).fillna('shallu')
        d5=df5.loc[df5['Distance'] == "shallu"]
        df5.to_excel('Backend//Result_Sheet12.xlsx',
                         sheet_name='Warehouse_FPS')

        # ----------------------------------------------------------------------------------------------------------------------------------------------
# -------------------- READ INPUT --------------------
        Result_Sheet1 = pd.ExcelFile("Backend//Result_Sheet12.xlsx")
        df6 = pd.read_excel(Result_Sheet1, sheet_name="Warehouse_FPS")
        Result_Sheet1.close()

        df7 = df6.loc[df6['Distance'] == "shallu"].reset_index(drop=True)

        # -------------------- API Details --------------------
        auth_url = 'https://kerala.pmgatishakti.gov.in/DFPD/authenticate'
        distance_url = 'https://kerala.pmgatishakti.gov.in/PMGatishaktiApiService/dfpdapi/roaddistance'

        auth_payload = {
            "username": "DFPD_C",
            "password": "W9Vtb8WKkt3"
        }

        FILE_PATH = 'distanceIndent.json'

        # -------------------- Get Token --------------------
        def get_token():
            try:
                response = requests.post(auth_url, json=auth_payload, timeout=240)
                if response.status_code == 200:
                    return response.json().get('token')
                return None
            except requests.exceptions.RequestException as e:
                print("Auth API Error:", e)
                raise Exception("PMGatiShakti Authentication Service is currently unavailable. Please check your internet connection or try again later.")

        # -------------------- Batch API --------------------
        def process_batch(df_batch, token):
            headers = {'Authorization': f'Bearer {token}'}

            data = {
                "parameter": [{
                    "src_lng": row["From_Long"],
                    "src_lat": row["From_Lat"],
                    "dest_lng": row["To_Long"],
                    "dest_lat": row["To_Lat"]
                } for _, row in df_batch.iterrows()]
            }

            try:
                with open(FILE_PATH, 'w') as f:
                    json.dump(data, f, indent=4)

                with open(FILE_PATH, 'rb') as f:
                    files = {'LatsLongsFile': f}
                    response = requests.post(distance_url, headers=headers, files=files, timeout=240)

                return response
            except requests.exceptions.RequestException as e:
                print("Batch API Error:", e)
                raise Exception("PMGatiShakti Distance Service is currently unavailable. Please check your internet connection or try again later.")
            except Exception as e:
                print("Batch API Error:", e)
                return None

        # -------------------- Single Row API --------------------
        def process_single_row(row, token):
            headers = {'Authorization': f'Bearer {token}'}

            data = {
                "parameter": [{
                    "src_lng": row["From_Long"],
                    "src_lat": row["From_Lat"],
                    "dest_lng": row["To_Long"],
                    "dest_lat": row["To_Lat"]
                }]
            }

            try:
                with open(FILE_PATH, 'w') as f:
                    json.dump(data, f, indent=4)

                with open(FILE_PATH, 'rb') as f:
                    files = {'LatsLongsFile': f}
                    response = requests.post(distance_url, headers=headers, files=files, timeout=240)

                if response.status_code != 200:
                    return 0

                res_json = response.json()
                api_data = res_json.get("data", [])

                if len(api_data) == 0:
                    return 0

                distance = api_data[0].get("distance")

                if isinstance(distance, (int, float)):
                    return distance

                return 0
            except requests.exceptions.RequestException as e:
                print("Row API Error (Connection):", e)
                return "CONNECTION_ERROR"
            except Exception as e:
                print("Row API Error:", e)
                return 0

        # ----------------------------------------------------------------------------------------------------------------------------------------------
        # -------------------- MAIN PROCESS --------------------

        batch_size = 1000
        total_rows = len(df7)
        num_batches = (total_rows + batch_size - 1) // batch_size

        dist3 = []

        for batch_num in range(num_batches):
            print(f"Processing batch {batch_num+1}/{num_batches}")

            start_idx = batch_num * batch_size
            end_idx = min((batch_num + 1) * batch_size, total_rows)
            df_batch = df7.iloc[start_idx:end_idx]

            token = get_token()
            if not token:
                data_err = {"status": 0, "message": "Failed to retrieve PMGatiShakti token."}
                return json.dumps(data_err, indent=1)

            response = process_batch(df_batch, token)

            fallback_required = False

            if not response or response.status_code != 200:
                fallback_required = True
            else:
                try:
                    response_json = response.json()
                    api_data = response_json.get("data", [])

                    if len(api_data) != len(df_batch):
                        fallback_required = True
                    else:
                        for row_data in api_data:
                            distance = row_data.get("distance")
                            if not isinstance(distance, (int, float)):
                                fallback_required = True
                                break

                except Exception:
                    fallback_required = True

            # ---------------- FALLBACK ----------------
            if fallback_required:
                print(f"Batch {batch_num+1} failed -> switching to row-wise")

                for _, row in df_batch.iterrows():
                    distance = process_single_row(row, token)
                    if distance == "CONNECTION_ERROR":
                        data_err = {"status": 0, "message": "PMGatiShakti API is currently unavailable or there is an internet connection issue. Please check your connection and try again."}
                        return json.dumps(data_err, indent=1)

                    if distance == 0:
                        print(f"Distance set to 0 for From {row['From_ID']} -> To {row['To_ID']}")

                    dist3.append(distance)

            # ---------------- NORMAL ----------------
            else:
                for row_data in api_data:
                    dist3.append(row_data.get("distance"))

       

        # -------------------- UPDATE DATA --------------------
        df7["Distance"] = dist3

        df9 = df6.loc[df6['Distance'] != "shallu"]

        columns = [
            'Scenario','From','From_State','From_District','From_ID','From_Name',
            'From_Lat','From_Long','To','To_ID','To_Name','To_State','To_District',
            'To_Lat','To_Long','commodity','quantity','Distance'
        ]

        df9 = df9[columns]
        df7 = df7[columns]

        df10 = pd.concat([df9, df7], ignore_index=True)

        # -------------------- FINAL RESULT --------------------
        result = (df10['quantity'] * df10['Distance']).sum()

        print("Total Result:", result)

        # -------------------- SAVE OUTPUT --------------------
        df10.to_excel('Backend//Result_Sheet_leg1.xlsx', sheet_name='Warehouse_FPS', index=False)

        print("Process Completed Successfully")
# ---------------------------------------------                     
        Total_Demand=  float(WH['Allocation_Wheat'].sum()) + float(WH['Allocation_Rice'].sum())       

        data ={}        
        
        data["Scenario"]="Inter"
        data["Scenario_Baseline"] = "Baseline"
        
        data["WH_Used"] = df5['From_ID'].nunique()
        data["WH_Used_Baseline"] = "5"
        
        data["FPS_Used"] = df5['To_ID'].nunique()
        data["FPS_Used_Baseline"] = "76"
        
        data['Demand'] = Total_Demand
        data['Demand_Baseline'] = "69,247"
        
        
        
        data['Total_QKM'] = float(result)
        data['Total_QKM_Baseline'] = "67,16,263"
        
        data['Average_Distance'] = float(round(result, 2)) / Total_Demand
        data['Average_Distance_Baseline'] = "96.66"

        if stop_process==True or is_job_cancelled():
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)                     
        
        
        save_to_database_leg1(month, year, applicable)
        save_monthly_data_leg1(month, year, float(result))
        
        def delete_files(file_paths):
            for file_path in file_paths:
                try:
                    if os.path.exists(file_path):  # Check if the file exists
                        os.remove(file_path)  # Delete the file
                        #print(f"{file_path} has been deleted.")
                    else:
                        print(f"{file_path} does not exist.")
                except Exception as e:
                    print(f"Error deleting {file_path}: {e}")

        # List of files to delete
        files_to_delete = [
            'Backend/Bihar_Distance_L1.xlsx',
            'Backend/Result_Sheet12.xlsx',
            'Backend//Tagging_Sheet_Pre11_leg1.xlsx',
            
        ]

        # Call the function to delete the files
        delete_files(files_to_delete)
        
        
        json_data = json.dumps(data)
        json_object = json.loads(json_data)

        if os.path.exists('ouputPickle.pkl'):
            os.remove('ouputPickle.pkl')

        # open pickle file
        dbfile1 = open('ouputPickle.pkl', 'ab')

    # save pickle data
    pickle.dump(json_object, dbfile1)
    dbfile1.close()
    data['status'] = 1
    json_data = json.dumps(data)
    json_object = json.loads(json_data)
    return json.dumps(json_object, indent=1)
    


if __name__ == "__main__":
    multiprocessing.freeze_support()
    if len(sys.argv) > 2 and sys.argv[1] == "--run-job":
        job_id = sys.argv[2]
        parent_instance_id = sys.argv[3] if len(sys.argv) > 3 else None
        _run_job_from_cli(job_id, parent_instance_id)
    else:
        _job_db_init()
        _job_reconcile_after_restart()
        _job_prune_old(days=30)
        app.run(host='0.0.0.0', port=5000)
# -*- coding: utf-8 -*-

    