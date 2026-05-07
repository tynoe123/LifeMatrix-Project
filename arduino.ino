#include <Wire.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include <Adafruit_SSD1306.h>
#include <Adafruit_MPU6050.h>
#include <Adafruit_Sensor.h>
#include <OneWire.h>
#include <DallasTemperature.h>
#include "MAX30105.h"
#include <HardwareSerial.h>

// ================= PIN CONFIG =================
#define SDA_PIN 4
#define SCL_PIN 5
#define ONE_WIRE_BUS 6

int buzzer = 8;

// GSM
#define GSM_TX 2
#define GSM_RX 3

// ================= WIFI =================
const char* ssid     = "heart";
const char* password = "12345678";

// ================= SERVER =================
const char* serverName = "https://freelanceelectronics.co.zw/patient/api/save_readings.php";
const char* patientID  = "PATIENT001";

// ================= ALERT =================
const char* alertNumber = "+263777055889";

// ================= OLED =================
#define SCREEN_WIDTH 128
#define SCREEN_HEIGHT 64
Adafruit_SSD1306 display(SCREEN_WIDTH, SCREEN_HEIGHT, &Wire, -1);

// ================= SENSORS =================
Adafruit_MPU6050 mpu;
MAX30105 max30102;
OneWire oneWire(ONE_WIRE_BUS);
DallasTemperature tempSensor(&oneWire);

// ================= GSM =================
HardwareSerial GSM(1);

// ================= VARIABLES =================
float temperatureC   = 0;
int   heartRateBPM   = 0;
int   spo2           = 0;
bool  fallDetected   = false;
bool  wristDetected  = false;
float zAxis          = 0;

// ================== NEW: FRESH HR FLAG =================
bool freshHRReading  = false;   // true only when heartRateBPM has just changed
// =======================================================

// --- Sampling ---
#define SAMPLE_RATE_HZ      50
#define SAMPLE_INTERVAL_MS  20
unsigned long lastSampleTime = 0;

// --- DC removal (IIR high-pass) ---
#define DC_ALPHA            0.97f
float   dcValue             = 0.0f;
bool    dcInitialised       = false;

// --- Low-pass smoothing ---
#define LP_SIZE   4
float   lpBuf[LP_SIZE]      = {0};
int     lpIdx               = 0;

// --- Motion gating ---
#define MOTION_THRESHOLD    0.12f
float   accelMagWrist       = 0.0f;
bool    motionDetected      = false;

// --- Peak detection ---
#define MIN_RR_MS           333
#define PEAK_THRESHOLD_FRAC 0.40f
float   peakAmplitude       = 0.0f;
float   dynamicThreshold    = 0.0f;
bool    aboveThreshold      = false;
unsigned long lastPeakTime  = 0;

// --- RR interval validation ---
unsigned long lastRR_ms     = 0;
#define MAX_RR_CHANGE_FRAC  0.40f

// --- BPM median filter ---
#define BPM_HISTORY         8
int  bpmHistory[BPM_HISTORY] = {0};
int  bpmHistIdx              = 0;
bool bpmHistFilled           = false;

// --- Wrist detection ---
#define WRIST_IR_THRESHOLD  5000

// --- SpO2 ---
#define SPO2_AC_WINDOW      50
float   irACbuf[SPO2_AC_WINDOW];
float   redACbuf[SPO2_AC_WINDOW];
float   dcIR_spo2           = 0.0f;
float   dcRed_spo2          = 0.0f;
int     spo2SampleCount     = 0;

// ================= NORMAL RANGES =================
#define TEMP_NORMAL_LOW   32.0
#define TEMP_NORMAL_HIGH  34
#define HR_NORMAL_LOW     60
#define HR_NORMAL_HIGH    120
#define SPO2_NORMAL_LOW   90

bool alertSent    = false;
unsigned long lastUpload = 0;

// ================= FALL DETECTION VARIABLES =================
float accelMagnitude      = 0;
unsigned long impactTime  = 0;
bool waitingForStability  = false;
bool buzzerActive         = false;
unsigned long buzzerStateTime = 0;
bool buzzerOn             = false;
bool potentialFall        = false;
unsigned long fallConfirmTime = 0;
unsigned long lastSittingCheck = 0;
bool sittingDetected      = false;
unsigned long sittingStartTime = 0;

float accelHistory[5]  = {0};
int   historyIndex     = 0;
bool  historyFilled    = false;

// ================= FALL DETECTION THRESHOLDS =================
#define IMPACT_THRESHOLD    2.5
#define STABILITY_WAIT_MS   1500
#define LYING_THRESHOLD     0.9
#define SITTING_THRESHOLD   1.0
#define SITTING_CONFIRM_MS  2000
#define FALL_RESET_TIME     10000

// ================= TEMPERATURE ASYNC =================
#define TEMP_CONVERSION_MS  188
unsigned long tempRequestTime = 0;
bool tempRequested = false;


// =====================================================
// ====================== SETUP ========================
// =====================================================

void setup() {
  Serial.begin(115200);
  Wire.begin(SDA_PIN, SCL_PIN);

  display.begin(SSD1306_SWITCHCAPVCC, 0x3C);
  display.setTextSize(1);
  display.setTextColor(WHITE);
  display.clearDisplay();
  display.setCursor(0, 0);
  display.println("Initialising...");
  display.display();

  pinMode(buzzer, OUTPUT);
  digitalWrite(buzzer, LOW);

  if (!mpu.begin()) {
    Serial.println("Failed to find MPU6050");
    while (1) { delay(10); }
  }
  mpu.setAccelerometerRange(MPU6050_RANGE_2_G);
  mpu.setFilterBandwidth(MPU6050_BAND_10_HZ);
  Serial.println("MPU6050 OK");

  tempSensor.begin();

  if (!max30102.begin(Wire, I2C_SPEED_FAST)) {
    Serial.println("Failed to find MAX30102");
  } else {
    byte ledBrightness = 255;
    byte sampleAverage = 1;
    byte ledMode       = 2;
    int  sampleRate    = 50;
    int  pulseWidth    = 411;
    int  adcRange      = 16384;
    max30102.setup(ledBrightness, sampleAverage, ledMode,
                   sampleRate, pulseWidth, adcRange);
    Serial.println("MAX30102 OK: wrist mode, 50Hz, max LED, max ADC");
  }

  GSM.begin(9600, SERIAL_8N1, GSM_RX, GSM_TX);
  delay(500);

  connectWiFi();

  display.clearDisplay();
  display.setCursor(0, 20);
  display.println("Wear device on");
  display.println("wrist snugly.");
  display.println("Warming up...");
  display.display();

  memset(irACbuf,  0, sizeof(irACbuf));
  memset(redACbuf, 0, sizeof(redACbuf));
}


// =====================================================
// ======================= LOOP ========================
// =====================================================

void loop() {
  readTemperature();
  readAccelForMotionGate();
  collectWristPPG();
  detectFallSimple();
  handleBuzzer();
  updateDisplay();
  checkAlerts();

  if (millis() - lastUpload > 15000) {
    sendToServer();
    lastUpload = millis();
  }
}


// =====================================================
// ============= MOTION GATE (MPU6050) =================
// =====================================================

void readAccelForMotionGate() {
  sensors_event_t a, g, t;
  mpu.getEvent(&a, &g, &t);

  float ax = a.acceleration.x / 9.81f;
  float ay = a.acceleration.y / 9.81f;
  float az = a.acceleration.z / 9.81f;
  zAxis = az;

  accelMagWrist = sqrt(ax*ax + ay*ay + az*az);
  float deviation = fabs(accelMagWrist - 1.0f);
  motionDetected = (deviation > MOTION_THRESHOLD);
}


// =====================================================
// ============= WRIST PPG COLLECTION ==================
// =====================================================

void collectWristPPG() {
  unsigned long now = millis();
  if (now - lastSampleTime < SAMPLE_INTERVAL_MS) return;
  lastSampleTime = now;

  int samplesAvailable = max30102.check();
  if (samplesAvailable == 0) return;

  while (max30102.available()) {
    uint32_t irRaw  = max30102.getFIFOIR();
    uint32_t redRaw = max30102.getFIFORed();
    max30102.nextSample();

    if (irRaw < WRIST_IR_THRESHOLD) {
      wristDetected  = false;
      heartRateBPM   = 0;
      spo2           = 0;
      dcInitialised  = false;
      peakAmplitude  = 0;
      aboveThreshold = false;
      bpmHistIdx     = 0;
      bpmHistFilled  = false;
      memset(bpmHistory, 0, sizeof(bpmHistory));
      Serial.println("Sensor not detected on wrist");
      return;
    }
    wristDetected = true;

    if (!dcInitialised) {
      dcValue       = (float)irRaw;
      dcIR_spo2     = (float)irRaw;
      dcRed_spo2    = (float)redRaw;
      dcInitialised = true;
    }
    dcValue    = DC_ALPHA * dcValue    + (1.0f - DC_ALPHA) * (float)irRaw;
    dcIR_spo2  = DC_ALPHA * dcIR_spo2  + (1.0f - DC_ALPHA) * (float)irRaw;
    dcRed_spo2 = DC_ALPHA * dcRed_spo2 + (1.0f - DC_ALPHA) * (float)redRaw;

    float acIR  = (float)irRaw  - dcValue;
    float acRed = (float)redRaw - dcRed_spo2;

    lpBuf[lpIdx] = acIR;
    lpIdx = (lpIdx + 1) % LP_SIZE;
    float smoothAC = 0;
    for (int i = 0; i < LP_SIZE; i++) smoothAC += lpBuf[i];
    smoothAC /= LP_SIZE;

    irACbuf[spo2SampleCount  % SPO2_AC_WINDOW] = acIR;
    redACbuf[spo2SampleCount % SPO2_AC_WINDOW] = acRed;
    spo2SampleCount++;

    if (spo2SampleCount % SPO2_AC_WINDOW == 0) {
      computeSpo2Estimate();
    }

    if (motionDetected) {
      Serial.println("Motion artifact — peak detection paused");
      peakAmplitude *= 0.90f;
      return;
    }

    peakAmplitude *= 0.998f;
    if (smoothAC > peakAmplitude) peakAmplitude = smoothAC;

    dynamicThreshold = peakAmplitude * PEAK_THRESHOLD_FRAC;

    if (peakAmplitude < 80.0f) {
      Serial.print("Signal too weak: ");
      Serial.println(peakAmplitude);
      return;
    }

    bool crossingUp = (!aboveThreshold && smoothAC > dynamicThreshold);
    bool crossingDn = ( aboveThreshold && smoothAC < dynamicThreshold);

    if (crossingUp) aboveThreshold = true;

    if (crossingDn) {
      aboveThreshold = false;
      unsigned long rrInterval = now - lastPeakTime;

      if (rrInterval >= MIN_RR_MS && lastPeakTime > 0) {
        bool rrValid = true;
        if (lastRR_ms > 0) {
          float change = fabs((float)rrInterval - (float)lastRR_ms) / (float)lastRR_ms;
          if (change > MAX_RR_CHANGE_FRAC) {
            rrValid = false;
            Serial.print("RR rejected (change=");
            Serial.print(change * 100, 1);
            Serial.println("%)");
          }
        }

        if (rrValid) {
          lastRR_ms = rrInterval;
          int instantBPM = (int)(60000UL / rrInterval);

          if (instantBPM >= 40 && instantBPM <= 180) {
            bpmHistory[bpmHistIdx] = instantBPM;
            bpmHistIdx = (bpmHistIdx + 1) % BPM_HISTORY;
            if (bpmHistIdx == 0) bpmHistFilled = true;

            int filled = bpmHistFilled ? BPM_HISTORY : bpmHistIdx;
            if (filled > 0) {
              int newHR = medianBPM(filled);

              // ================== CHANGE 1 OF 2 ==================
              // Only flag as fresh if the HR value has actually changed
              if (newHR != heartRateBPM) {
                heartRateBPM   = newHR;
                freshHRReading = true;
              }
              // ====================================================

              Serial.print("Peak! RR=");
              Serial.print(rrInterval);
              Serial.print("ms  Instant=");
              Serial.print(instantBPM);
              Serial.print("  Median HR=");
              Serial.println(heartRateBPM);
            }
          }
        }
      }
      lastPeakTime = now;
    }

    static unsigned long lastDebugPPG = 0;
    if (now - lastDebugPPG > 200) {
      Serial.print("IR="); Serial.print(irRaw);
      Serial.print(" DC="); Serial.print((int)dcValue);
      Serial.print(" AC="); Serial.print(smoothAC, 1);
      Serial.print(" Thr="); Serial.print(dynamicThreshold, 1);
      Serial.print(" Mot="); Serial.println(motionDetected ? "YES" : "NO");
      lastDebugPPG = now;
    }
  }
}


// =====================================================
// ======== SpO2 ESTIMATE (display only) ===============
// =====================================================

void computeSpo2Estimate() {
  float irMin = irACbuf[0],  irMax = irACbuf[0];
  float redMin = redACbuf[0], redMax = redACbuf[0];

  for (int i = 1; i < SPO2_AC_WINDOW; i++) {
    if (irACbuf[i]  < irMin)  irMin  = irACbuf[i];
    if (irACbuf[i]  > irMax)  irMax  = irACbuf[i];
    if (redACbuf[i] < redMin) redMin = redACbuf[i];
    if (redACbuf[i] > redMax) redMax = redACbuf[i];
  }

  float acIR  = (irMax  - irMin)  / 2.0f;
  float acRed = (redMax - redMin) / 2.0f;

  if (dcIR_spo2 < 100 || dcRed_spo2 < 100) return;
  if (acIR < 10 || acRed < 10) return;

  float R = (acRed / dcRed_spo2) / (acIR / dcIR_spo2);
  int est = (int)(104.0f - 17.0f * R);
  if (est >= 70 && est <= 100) {
    spo2 = est;
    Serial.print("SpO2 estimate: ");
    Serial.println(spo2);
  }
}


// =====================================================
// ============= MEDIAN FILTER FOR BPM =================
// =====================================================

int medianBPM(int count) {
  int temp[BPM_HISTORY];
  memcpy(temp, bpmHistory, count * sizeof(int));
  for (int i = 1; i < count; i++) {
    int key = temp[i], j = i - 1;
    while (j >= 0 && temp[j] > key) { temp[j+1] = temp[j]; j--; }
    temp[j+1] = key;
  }
  return temp[count / 2];
}


// =====================================================
// ================= TEMPERATURE =======================
// =====================================================

void readTemperature() {
  unsigned long now = millis();
  if (!tempRequested) {
    tempSensor.setWaitForConversion(false);
    tempSensor.setResolution(9);
    tempSensor.requestTemperatures();
    tempRequestTime = now;
    tempRequested   = true;
    return;
  }
  if (now - tempRequestTime >= TEMP_CONVERSION_MS) {
    float t = tempSensor.getTempCByIndex(0);
    if (t != -127.00f && t != 85.00f) temperatureC = t;
    tempRequested = false;
  }
}


// =====================================================
// ================= BUZZER HANDLER ====================
// =====================================================

void handleBuzzer() {
  if (fallDetected) {
    if (!buzzerActive) {
      buzzerActive    = true;
      buzzerOn        = true;
      digitalWrite(buzzer, HIGH);
      buzzerStateTime = millis();
    } else {
      if (buzzerOn && (millis() - buzzerStateTime >= 3000)) {
        digitalWrite(buzzer, LOW);
        buzzerOn        = false;
        buzzerStateTime = millis();
      } else if (!buzzerOn && (millis() - buzzerStateTime >= 1000)) {
        digitalWrite(buzzer, HIGH);
        buzzerOn        = true;
        buzzerStateTime = millis();
      }
    }
  } else {
    digitalWrite(buzzer, LOW);
    buzzerActive = false;
    buzzerOn     = false;
  }
}


// =====================================================
// ================= FALL DETECTION ====================
// =====================================================

void detectFallSimple() {
  sensors_event_t a, g, t;
  mpu.getEvent(&a, &g, &t);

  float ax = a.acceleration.x / 9.81;
  float ay = a.acceleration.y / 9.81;
  float az = a.acceleration.z / 9.81;
  zAxis = az;

  float currentMagnitude = sqrt(ax*ax + ay*ay + az*az);

  accelHistory[historyIndex] = currentMagnitude;
  historyIndex = (historyIndex + 1) % 5;
  if (historyIndex == 0) historyFilled = true;

  float smoothedMagnitude = currentMagnitude;
  if (historyFilled) {
    smoothedMagnitude = 0;
    for (int i = 0; i < 5; i++) smoothedMagnitude += accelHistory[i];
    smoothedMagnitude /= 5;
  }
  accelMagnitude = smoothedMagnitude;

  checkSittingPosition(az);

  if (!waitingForStability && !potentialFall) {
    if (accelMagnitude > IMPACT_THRESHOLD) {
      impactTime          = millis();
      potentialFall       = true;
      waitingForStability = true;
      Serial.println("*** IMPACT DETECTED ***");
    }
  } else if (potentialFall && waitingForStability) {
    if (millis() - impactTime > STABILITY_WAIT_MS) {
      float gravityComponent = abs(zAxis);
      bool  isLyingDown      = (gravityComponent < LYING_THRESHOLD);
      if (isLyingDown) {
        fallDetected    = true;
        fallConfirmTime = millis();
        Serial.println("*** FALL CONFIRMED ***");
      } else {
        fallDetected = false;
        Serial.println("False alarm — not lying down");
      }
      potentialFall       = false;
      waitingForStability = false;
    }
  }

  if (fallDetected && (millis() - fallConfirmTime > FALL_RESET_TIME)) {
    fallDetected = false;
    Serial.println("Fall alert auto-reset");
  }
}

void checkSittingPosition(float zAxisVal) {
  unsigned long currentTime = millis();
  if (currentTime - lastSittingCheck < 500) return;
  lastSittingCheck = currentTime;

  bool isSittingNow = (abs(zAxisVal) > SITTING_THRESHOLD);
  if (isSittingNow) {
    if (!sittingDetected) {
      sittingStartTime = currentTime;
      sittingDetected  = true;
    } else if (currentTime - sittingStartTime > SITTING_CONFIRM_MS) {
      if (potentialFall || waitingForStability) {
        Serial.println("Person upright — cancelling fall detection");
        potentialFall       = false;
        waitingForStability = false;
        fallDetected        = false;
      }
    }
  } else {
    sittingDetected = false;
  }
}


// =====================================================
// ================= VITAL STATUS HELPERS ==============
// =====================================================

bool isTempAbnormal() {
  return (temperatureC > 0 &&
          (temperatureC < TEMP_NORMAL_LOW || temperatureC > TEMP_NORMAL_HIGH));
}
bool isHRAbnormal() {
  return (heartRateBPM > 0 &&
          (heartRateBPM < HR_NORMAL_LOW || heartRateBPM > HR_NORMAL_HIGH));
}
bool isSpO2Abnormal() {
  return (spo2 > 0 && spo2 < SPO2_NORMAL_LOW);
}
bool allVitalsNormal() {
  return (!isTempAbnormal() && !isHRAbnormal() && !isSpO2Abnormal());
}


// =====================================================
// ================= DISPLAY ===========================
// =====================================================

void updateDisplay() {
  display.clearDisplay();
  display.setTextSize(1);
  display.setCursor(0, 0);

  if (!wristDetected) {
    display.setCursor(0, 20);
    display.println("Wear device on");
    display.println("wrist snugly");
    display.display();
    return;
  }

  if (motionDetected) {
    display.setCursor(0, 0);
    display.println("Keep wrist still...");
  }

  display.setTextSize(2);
  display.setCursor(0, 10);
  if (heartRateBPM > 0) {
    display.print(heartRateBPM);
    display.println(" BPM");
  } else {
    display.println("--- BPM");
  }

  display.setTextSize(1);
  display.setCursor(0, 40);
  display.print("T:");
  display.print(temperatureC, 1);
  display.print("C  ");

  if (spo2 > 0) {
    display.print("SpO2~");
    display.print(spo2);
    display.print("%");
  }

  display.setCursor(0, 55);
  if (fallDetected) {
    display.println("!! FALL DETECTED !!");
  } else {
    display.println("No Fall");
  }

  display.display();
}


// =====================================================
// ================= ALERT FUNCTION ====================
// =====================================================

void checkAlerts() {
  if (!fallDetected) { alertSent = false; return; }
  if (alertSent) return;

  String msg = "ALERT! PATIENT001 Tinotendaishe Masurani\n\n";
  msg += "FALL DETECTED!\n\n";

  if (allVitalsNormal()) {
    msg += "Vitals are NORMAL.\n";
  } else {
    msg += "ABNORMAL VITALS:\n";
    if (isTempAbnormal())
      msg += "  Temp: " + String(temperatureC, 1) + " C\n";
    if (isHRAbnormal())
      msg += "  HR: " + String(heartRateBPM) + " BPM\n";
    if (isSpO2Abnormal())
      msg += "  SpO2: " + String(spo2) + " % (estimate)\n";
  }

  msg += "\nReadings:\n";
  msg += "Temp: "  + String(temperatureC, 1) + " C\n";
  msg += "HR: "    + String(heartRateBPM) + " BPM\n";
  msg += "SpO2: ~" + String(spo2) + " %";

  sendSMS(alertNumber, msg);
  alertSent = true;
  Serial.println("*** SMS ALERT SENT ***");
}

void sendSMS(const char* number, String message) {
  GSM.println("AT");         delay(300);
  GSM.println("AT+CMGF=1"); delay(300);
  GSM.print("AT+CMGS=\"");  GSM.print(number); GSM.println("\""); delay(300);
  GSM.print(message);        delay(300);
  GSM.write(26);             delay(3000);
}


// =====================================================
// ================= CLOUD UPLOAD ======================
// =====================================================

void sendToServer() {
  if (heartRateBPM == 0) {
    Serial.println("Skipping upload: no valid HR yet");
    return;
  }
  if (WiFi.status() != WL_CONNECTED) { connectWiFi(); return; }

  HTTPClient http;
  http.begin(serverName);
  http.addHeader("Content-Type", "application/x-www-form-urlencoded");

  String postData  = "patient_id="   + String(patientID);
  postData        += "&heart_rate="  + String(heartRateBPM);
  postData        += "&spo2="        + String(spo2);
  postData        += "&temperature=" + String(temperatureC, 1);
  postData        += "&fall="        + String(fallDetected ? 1 : 0);

  // ================== CHANGE 2 OF 2 ==================
  // Send the fresh HR flag so the server can mark stale readings
  postData        += "&hr_fresh="    + String(freshHRReading ? 1 : 0);
  freshHRReading   = false;   // reset after every upload cycle
  // ====================================================

  int code = http.POST(postData);
  Serial.print("Server response: "); Serial.println(code);
  http.end();
}


// =====================================================
// ================= WIFI ==============================
// =====================================================

void connectWiFi() {
  display.clearDisplay();
  display.setCursor(0, 20);
  display.println("Connecting WiFi...");
  display.display();

  WiFi.begin(ssid, password);
  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 20) {
    delay(500); Serial.print("."); attempts++;
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\nWiFi Connected");
    display.clearDisplay();
    display.setCursor(0, 20);
    display.println("WiFi Connected");
    display.display();
  } else {
    Serial.println("\nWiFi Failed");
    display.clearDisplay();
    display.setCursor(0, 20);
    display.println("WiFi Failed");
    display.display();
  }
  delay(800);
}
